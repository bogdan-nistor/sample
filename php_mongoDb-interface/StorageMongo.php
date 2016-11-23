<?php
/**
 * class StorageMongo
 * provides a simple interface for PHP MongoClient
 * @package     Storage
 * @copyright   Copyright (c) 2015 Traffics Softwaresysteme für den Tourismus GmbH (http://www.traffics.de/)
 * @version     $Id: $
 * @author      Bogdan Nistor <bogdan.nistorb@yahoo.com>
 */
namespace Storage;
use MongoId;

class StorageMongo implements StorageInterface
{
    /**
     * @var StorageInterface $instance
     */
    static protected $instance;
    /**
     * @var \MongoClient
     */
    protected $client;
    /**
     * @var \MongoDB
     */
    protected $database;
    /**
     * @var \MongoCollection
     */
    protected $collection;

    /**
     * @param $options
     * @return StorageInterface
     */
    static public function getInstance($options = null)
    {
        if (!self::$instance) {
            self::$instance = new self($options);
        }
        return self::$instance;
    }

    /**
     * @param array $options
     */
    protected function __construct(array $options)
    {
        $this->client       = new \MongoClient($options['host']);
        $this->database     = $this->client->{$options['database']}; // Connect to Database
        $this->collection   = $this->database->{$options['collection']};
    }

    /**
     * @param array $params
     * @return MongoDocument[]
     */
    public function get($params = array())
    {
        $documents = array();
        foreach ($this->collection->find($params) as $document) {
            $documents[] = $this->mapToObject($document);
        }
        return $documents;
    }

    /**
     * @param MongoDocument $document
     * @return array|bool|mixed
     */
    public function put(MongoDocument $document)
    {
        if (empty($document->_id)) {
            $document->_id = new MongoId();
        }
        $_document = clone $document;
        $this->mapToJson($_document);
        return $this->collection->save($_document);
    }

    /**
     * @param array $params
     * @param array $options
     * @return array|bool|mixed
     */
    public function delete($params = array(), $options = array())
    {
        return $this->collection->remove($params, $options);
    }

    /**
     * @param MongoDocument $document
     * @return mixed
     */
    public function deleteDocument(MongoDocument $document)
    {
        return $this->collection->remove(array('_id' => $document->_id));
    }

    /**
     * @param array $query
     * @param array $fields
     * @return \MongoCursor
     */
    public function find($query = array(), $fields = array())
    {
        return $this->collection->find($query, $fields);
    }

    /**
     * @param MongoDocument|Object $object
     * Note:  _id und _type are reserved in MongoDB!!!
     */
    protected function mapToJson($object)
    {
        if (method_exists($object, 'onPut')) {
            call_user_func(array($object, 'onPut'));
        }
        $object->_class = get_class($object);
        foreach (get_object_vars($object) as $name => $var) {
            if (is_object($var)) {
                $this->mapToJson($var);
            }elseif (is_array($var)) {
                foreach ($var as $_var) {
                    if (is_object($_var)) {
                        $this->mapToJson($_var);
                    }
                }
                $object->{$name} = array_values($var);
            }
        }
    }

    /**
     * @param array $properties
     * @return object|null
     * Note:  _id und _type are reserved in MongoDB!!!
     */
    protected function mapToObject($properties)
    {
        if (isset($properties['_class'])) {
            $class = $properties['_class'];
            $object = new $class();
            foreach ($properties as $key => $val) {
                if ($val == $class) {continue;}
                if (is_array($val)) {
                    if (isset($val['_class'])) {
                        $object->{$key} = $this->mapToObject($val);
                    }else{
                        foreach ($val as $k => $_val) {
                            $id = ($_val['id']) ? $_val['id'] : $k;
                            $object->{$key}[$id] = $this->mapToObject($_val);;
                        }
                    }
                }else{
                    $object->{$key} = $val;
                }
            }
            if (method_exists($object, 'onGet')) {
                call_user_func(array($object, 'onGet'));
            }
            return $object;
        }else{
            return null;
        }
    }

}
