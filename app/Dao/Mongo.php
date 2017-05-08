<?php
namespace App\Dao;

use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Exception\InvalidArgumentException;
use MongoDB\Exception\RuntimeException;
use MongoDB\Exception\UnsupportedException;
use Psr\Container\ContainerInterface;

/**
 * mongo操作基类
 * 如有需要，请自行扩展
 * 文档地址：https://docs.mongodb.com/php-library/master/tutorial/install-php-library/
 */
class Mongo
{
    private $_di;
    private $_collection;
    private $_sequence;
    private $_seqId;

    /**
     * constructor receives container instance
     * @param ContainerInterface $di container instance
     * @param string $collection 集合名称
     * @param string $db 数据库配置名称，默认：mongo
     */
    public function __construct(ContainerInterface $di, $collection, $db = 'mongo'){
        $this->_di = $di;

        $mongo = $di->get($db);

        $settings = $di->get('settings')[$db];
        $db = $settings['database'];
        $table = sprintf("%s%s", $settings['prefix'], $collection);

        $this->_collection = $mongo->$db->$table;
        $this->_sequence = $mongo->$db->sequence;
        $this->_seqId = $collection;
    }

    /**
     * 插入单条记录
     * @param array $query 查询条件
     * @param array $data 插入数据
     * @return int/bool 影响的行数
     */
    protected function insert($data)
    {
        try {
            $id = $this->_refreshSequence();
            $data['_id'] = $id;
            $result = $this->_collection->insertOne($data);

            return $result->getInsertedId();
        } catch (InvalidArgumentException $e) {
            $this->_refreshSequence(-1);

            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] Insert Error: %s", $e->getMessage()));

            return false;
        } catch (BulkWriteException $e) {
            $this->_refreshSequence(-1);

            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] Insert Error: %s", $e->getMessage()));

            return false;
        } catch (RuntimeException $e) {
            $this->_refreshSequence(-1);

            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] Insert Error: %s", $e->getMessage()));

            return false;
        }
    }

    /**
     * 批量插入（失败数据会回滚）
     * @param array $query 查询条件
     * @param array $data 插入数据
     * @return int/bool 影响的行数
     */
    protected function batchInsert($data)
    {
        $count = count($data);

        try {
            foreach ($data as &$value) {
                $id = $this->_refreshSequence();
                $value['_id'] = $id;
            }

            $result = $this->_collection->insertMany($data);

            return $result->getInsertedCount();
        } catch (InvalidArgumentException $e) {
            $this->_refreshSequence(~$count + 1);

            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] BatchInsert Error: %s", $e->getMessage()));

            return false;
        } catch (BulkWriteException $e) {
            $this->_refreshSequence(~$count + 1);

            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] BatchInsert Error: %s", $e->getMessage()));

            return false;
        } catch (RuntimeException $e) {
            $this->_refreshSequence(~$count + 1);

            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] BatchInsert Error: %s", $e->getMessage()));

            return false;
        }
    }

    /**
     * 更新单条记录
     * @param array $query 查询条件
     * @param array $data 更新数据
     * @return int/bool 影响的行数
     */
    protected function update($query, $data)
    {
        try {
            $result = $this->_collection->updateOne($query, ['$set' => $data]);

            return $result->getModifiedCount();
        } catch (UnsupportedException $e) {
            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] Update Error: %s", $e->getMessage()));

            return false;
        } catch (InvalidArgumentException $e) {
            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] Update Error: %s", $e->getMessage()));

            return false;
        } catch (BulkWriteException $e) {
            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] Update Error: %s", $e->getMessage()));

            return false;
        } catch (RuntimeException $e) {
            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] Update Error: %s", $e->getMessage()));

            return false;
        }
    }

    /**
     * 批量更新
     * @param array $query 查询条件
     * @param array $data 更新数据
     * @return int/bool 影响的行数
     */
    protected function batchUpdate($query, $data)
    {
        try {
            $result = $this->_collection->updateMany($query, ['$set' => $data]);

            return $result->getModifiedCount();
        } catch (UnsupportedException $e) {
            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] BatchUpdate Error: %s", $e->getMessage()));

            return false;
        } catch (InvalidArgumentException $e) {
            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] BatchUpdate Error: %s", $e->getMessage()));

            return false;
        } catch (BulkWriteException $e) {
            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] BatchUpdate Error: %s", $e->getMessage()));

            return false;
        } catch (RuntimeException $e) {
            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] BatchUpdate Error: %s", $e->getMessage()));

            return false;
        }
    }

    /**
     * 查询单条记录
     * @param array $query 查询条件
     * @param array $options 查询可选项
     * @return MongoDB\Model\BSONDocument
     */
    protected function findOne($query, $options = [])
    {
        $data = $this->_collection->findOne($query, $options = []);

        return $data;
    }

    /**
     * 查询多条记录
     * @param array $query 查询条件
     * @param array $options 查询可选项
     * @return array
     */
    protected function find($query, $options = [])
    {
        $cursor = $this->_collection->find($query, $options = []);

        $data = [];

        foreach ($cursor as $doc) {
           $data[] = $doc;
        }

        return $data;
    }

    /**
     * 查询所有记录
     * @return array
     */
    protected function findAll()
    {
        $cursor = $this->_collection->find();

        $data = [];

        foreach ($cursor as $doc) {
           $data[] = $doc;
        }

        return $data;
    }

    /**
     * 删除单条记录
     * @param array $query 查询条件
     * @return int/bool 影响的行数
     */
    protected function delete($query)
    {
        try {
            $result = $this->_collection->deleteOne($query);

            return $result->getDeletedCount();
        } catch (UnsupportedException $e) {
            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] Delete Error: %s", $e->getMessage()));

            return false;
        } catch (InvalidArgumentException $e) {
            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] Delete Error: %s", $e->getMessage()));

            return false;
        } catch (BulkWriteException $e) {
            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] Delete Error: %s", $e->getMessage()));

            return false;
        } catch (RuntimeException $e) {
            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] Delete Error: %s", $e->getMessage()));

            return false;
        }
    }

    /**
     * 批量删除
     * @param array $query 查询条件
     * @return bool
     */
    protected function batchDelete($query)
    {
        try {
            $result = $this->_collection->deleteMany($query);

            return $result;
        } catch (UnsupportedException $e) {
            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] BatchDelete Error: %s", $e->getMessage()));

            return false;
        } catch (InvalidArgumentException $e) {
            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] BatchDelete Error: %s", $e->getMessage()));

            return false;
        } catch (BulkWriteException $e) {
            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] BatchDelete Error: %s", $e->getMessage()));

            return false;
        } catch (RuntimeException $e) {
            $logger = $this->_di->get('logger');
            $logger->error(sprintf("[Mongo] BatchDelete Error: %s", $e->getMessage()));

            return false;
        }
    }

    /**
     * 生成 Mongo 文档当前自增的_id值
     * @param int $inc 增量，默认：1
     * @return int 当前自增的_id值
     */
    private function _refreshSequence($inc = 1)
    {
        $this->_sequence->updateOne(
            ['_id' => $this->_seqId],
            ['$inc' => ['seq' => $inc]],
            ['upsert' => true]
        );

        $upsertedDocument = $this->_sequence->findOne([
            '_id' => $this->_seqId,
        ]);

        return $upsertedDocument->seq;
    }
}
?>