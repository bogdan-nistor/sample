#!/usr/bin/env python3

import os
import argparse
import math
import concurrent.futures
from filechunkio import FileChunkIO
from datetime import datetime, timedelta
from boto.s3.connection import S3Connection


class UploadDeliveries(object):
    """
    Gets tsv.gz files from S3
    """

    DATE_FORMAT = '%Y-%m-%d'
    UPLOAD_BUCKET_NAME = 'ultron-delivery-data-merged'
    PROCESS_OLDER_THAN = 14  # reason: delivery cleaner needs time to upload files to S3

    def __init__(self):
        conn = S3Connection(aws_access_key_id='***',
                            aws_secret_access_key='***')
        self.bucket = conn.get_bucket(self.UPLOAD_BUCKET_NAME)
        argvs = self.get_argv_list()
        self.download_path = argvs.download_path
        self.to_date = datetime.now().date() - timedelta(days=self.PROCESS_OLDER_THAN + int(argvs.days))

    def run(self):
        files = self.get_upload_files()
        if files:
            with concurrent.futures.ProcessPoolExecutor(len(files)) as executor:
                executor.map(self.s3_multipart_upload, files)

    def s3_multipart_upload(self, file_path):
        chunk_size = 1024 * 1024 * 80  # Min size permitted is 1024*1024*5 (5Mb)

        file_name = os.path.basename(file_path)
        file_size = os.stat(file_path).st_size
        try:
            if file_size > chunk_size:
                print('Uploading in chunks {} to S3 bucket {}. Chunk size {}.'
                      .format(file_path, self.UPLOAD_BUCKET_NAME, chunk_size))
                mp_request = self.bucket.initiate_multipart_upload(file_name)
                chunk_count = int(math.ceil(file_size / float(chunk_size)))
                for i in range(chunk_count):
                    offset = chunk_size * i
                    read_bytes = min(chunk_size, file_size - offset)
                    with FileChunkIO(file_path, 'r', offset=offset, bytes=read_bytes) as file_part:
                        mp_request.upload_part_from_file(file_part, part_num=i + 1)
                mp_request.complete_upload()
            else:
                print('Uploading file {} to S3 bucket {}.'.format(file_path, self.UPLOAD_BUCKET_NAME))
                new_key = self.bucket.new_key(file_name)
                new_key.set_contents_from_filename(file_path)
        except Exception as e:
            print('Could not upload file {}. {}'.format(file_path, e))
        else:
            print('Removing local file {}'.format(file_path))
            os.remove(file_path)

    def get_upload_files(self):
        upload_files = []
        # read download folder content for gzip files (no recursion)
        for file in os.listdir(self.download_path):
            if file.endswith(".gz"):
                if datetime.strptime(file.replace('.gz', ''), self.DATE_FORMAT).date() < self.to_date:
                    upload_files.append('{}/{}'.format(self.download_path, file))
        return upload_files

    @staticmethod
    def get_argv_list():
        parser = argparse.ArgumentParser(description='Upload merged deliveries to S3.')
        parser.add_argument('days', metavar='days', help='How old should be the files for upload')
        parser.add_argument('download_path', metavar='path', help='Folder that contains data')
        return parser.parse_args()


if __name__ == '__main__':
    upload_deliveries = UploadDeliveries()
    upload_deliveries.run()
