#!/usr/bin/env python3

import os
import shutil
import argparse
import gzip
from datetime import timedelta
import concurrent.futures
from uuid import uuid4
from collections import defaultdict
from heapq import merge
from contextlib import ExitStack
from datetime import datetime
from boto.s3.connection import S3Connection


class MergeDeliveries(object):

    def __init__(self):
        self.folders = defaultdict(list)
        self.threads_opened = 1
        self.header = None

    def each_file_processing(self, file_path, folder_path):
        try:
            with gzip.open(file_path) as input_file:
                row = next(input_file, None)  # skip header
                if row:
                    self.header = row
                    sorted_chunk = sorted(input_file, key=lambda l: l.split(b'\t')[9])  # sort by index 9, delivery_time
                    with gzip.open(file_path, 'wb') as output_file:
                        output_file.write(self.header)
                        output_file.writelines(sorted_chunk)
                        self.folders[folder_path].append(file_path)
        except Exception as e:
            std('File {} could be processed. {}'.format(file_path, e))

    def post_processing(self):
        for folder_path, folder_list in self.folders.items():
            merging_file_path = folder_path + '.gz'

            # merge chunks to avoid OSError:[Errno 24] Too many open files
            chunk_size = int(2000 / self.threads_opened)
            chunk_files = []
            chunk_list = [folder_list[i:i + chunk_size] for i in range(0, len(folder_list), chunk_size)]
            std('Starting to merge {}'.format(merging_file_path))

            for chunk_items in chunk_list:
                tmp_file = merging_file_path + str(uuid4())
                tmp_merged_file = self.heap_merge_files(tmp_file, chunk_items)
                if tmp_merged_file:
                    chunk_files.append(tmp_merged_file)
                else:
                    os.remove(tmp_file)

            main_merge_file = self.heap_merge_files(merging_file_path, chunk_files)
            if main_merge_file:
                try:
                    shutil.rmtree(folder_path)  # remove processed folder
                except Exception as e:
                    std('Could not remove folder {}. {}'.format(folder_path, e))

            for file in chunk_files:  # remove temporary files
                os.remove(file)

            std('Finished thread for processing folder {}'.format(folder_path))

    def heap_merge_files(self, result_file, file_list):
        with ExitStack() as stack, gzip.open(result_file, 'wb') as output_file:
            files = []
            try:
                for chunk in file_list:
                    fh = gzip.open(chunk)
                    next(fh)  # skip headers
                    files.append(stack.enter_context(fh))
                output_file.write(self.header)
                output_file.writelines(merge(*files, key=lambda l: l.split(b'\t')[9]))
            except Exception as e:
                std('Merge failed for {}. {}'.format(result_file, e))
                return None
        return result_file


class ProcessS3Deliveries(object):
    """
    Gets tsv.gz files from S3 and merges sorted by delivery time
    """

    DATE_FORMAT = '%Y-%m-%d'
    BUCKET_NAME = 'ultron-delivery-data'
    PROCESS_OLDER_THAN = 14  # reason: delivery cleaner needs time to upload files to S3

    def __init__(self):
        conn = S3Connection(aws_access_key_id='***',
                            aws_secret_access_key='***')
        argvs = self.get_argv_list()
        if 30 >= int(argvs.days) > 0:  # maximum of 30 days should be processed at once due to performance
            self.download_path = argvs.download_path
            self.bucket = conn.get_bucket(self.BUCKET_NAME)
            self.to_date = datetime.now().date() - timedelta(days=self.PROCESS_OLDER_THAN)
            self.from_date = self.to_date - timedelta(days=int(argvs.days))
        else:
            std('Maximum of 30 days should be processed at once due to performance!')

    def run(self):
        self.find_files(MergeDeliveries())

    def find_files(self, process_deliveries):
        bucket_folders = self.filter_folders()
        if bucket_folders:
            number_threads = len(bucket_folders)
            process_deliveries.threads_opened = number_threads
            # start parallel merge for each folder
            with concurrent.futures.ThreadPoolExecutor(max_workers=number_threads) as executor:
                executor.map(self.multi_thread_process, bucket_folders, [process_deliveries] * number_threads)
            process_deliveries.post_processing()

    def multi_thread_process(self, folder, process_deliveries):
        folder_path = self.check_folder_created(folder)
        if folder_path:
            std('Opening thread for processing folder {}'.format(folder_path))
            for file in self.bucket.list(folder.name):
                file_path = self.check_file_created(file)
                if file_path:
                    process_deliveries.each_file_processing(file_path, folder_path)

    def filter_folders(self):
        interval_folders = []
        folder_list = list(self.bucket.list('', '/'))
        for folder in folder_list:
            folder_name = folder.name.replace('/', '')
            folder_date = datetime.strptime(folder_name, self.DATE_FORMAT)
            if self.from_date <= folder_date <= self.to_date:
                if not os.path.isfile('{}/{}.gz'.format(self.download_path, folder_name)):
                    interval_folders.append(folder)
        return interval_folders

    def check_folder_created(self, folder):
        folder_path = '{}/{}'.format(self.download_path, folder.name)
        if not os.path.exists(folder_path):
            try:
                os.makedirs(folder_path)
            except:
                std('Do not have permission to create folder in specified location.')
                return None
        return folder_path[:-1]

    def check_file_created(self, file):
        file_path = '{}/{}'.format(self.download_path, file.name)
        if not os.path.isfile(file_path):
            try:
                file.get_contents_to_filename(file_path)
            except Exception as e:
                std('Download from S3 bucket failed. Message: {}'.format(e))
                return None
        return file_path

    @staticmethod
    def get_argv_list():
        parser = argparse.ArgumentParser(description='Downloads deliveries from S3, merges sorted by delivery time.')
        parser.add_argument('days', metavar='days', help='How many days to store')
        parser.add_argument('download_path', metavar='path', help='Folder that contains data')
        return parser.parse_args()


def std(message):
    print('{} - {}'.format(datetime.now(), message))


if __name__ == '__main__':
    offline_evaluation = ProcessS3Deliveries()
    offline_evaluation.run()
