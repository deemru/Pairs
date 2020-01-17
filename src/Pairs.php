<?php

namespace deemru;

class Pairs
{
    public function __construct( $db, $name, $writable = 0, $type = 'INTEGER PRIMARY KEY|TEXT UNIQUE|0|0', $cacheSize = 1024 )
    {
        if( is_string( $db ) )
        {
            $this->db = new \PDO( "sqlite:$db" );
            $this->db->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING );
            if( defined( 'PDO_FETCH_NUM' ) )
                $this->db->setAttribute( \PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_NUM );
            $this->db->exec( 'PRAGMA temp_store = MEMORY' );
        }
        else if( is_a( $db, __CLASS__ ) )
        {
            $this->db = $db->db;
            $this->child = true;
            $this->parent = $db;
        }
        else if( is_a( $db, 'PDO' ) )
        {
            $this->db = $db;
            $this->child = true;
        }
        else
        {
            throw new Exception( __CLASS__ . ': unsupported db type' );
        }

        $this->name = $name;
        $this->cacheSize = $cacheSize;
        $this->cacheByKey = [];

        if( $writable )
        {
            $this->db->exec( 'PRAGMA synchronous = NORMAL; PRAGMA journal_mode = WAL; PRAGMA journal_size_limit = 1048576; PRAGMA optimize;' );
            $this->type = explode( '|', $type );
            $this->db->exec( "CREATE TABLE IF NOT EXISTS {$this->name}( key {$this->type[0]}, value {$this->type[1]} )" );
            if( $this->type[2] )
                $this->db->exec( "CREATE INDEX IF NOT EXISTS {$this->name}_key_index ON {$this->name}( key )" );
            if( $this->type[3] )
                $this->db->exec( "CREATE INDEX IF NOT EXISTS {$this->name}_value_index ON {$this->name}( value )" );
            if( $this->type[3] || false !== strpos( $this->type[1], 'UNIQUE' ) )
                $this->cacheByValue = [];

            if( $writable > 1 )
            {
                if( !isset( $this->child ) )
                    $this->db->exec( "ATTACH DATABASE ':memory:' AS cache" );

                $keyType = strpos( $this->type[0], 'INTEGER' ) !== false ? 'INTEGER' : 'BLOB';
                $valueType = strpos( $this->type[1], 'INTEGER' ) !== false ? 'INTEGER' : 'BLOB';
                $this->db->exec( "CREATE TABLE cache.{$this->name}( key $keyType, value $valueType )" );
            }
        }
    }

    public function reset()
    {
        $this->db->exec( "DELETE FROM {$this->name}" );
        $this->resetCache();
    }

    public function db()
    {
        return $this->db;
    }

    public function begin()
    {
        return $this->db->beginTransaction();
    }

    public function commit()
    {
        return $this->db->commit();
    }

    public function rollback()
    {
        return $this->db->rollBack();
    }

    public function getKey( $value, $add = false, $int = true )
    {
        if( isset( $this->cacheByValue[$value] ) )
            return $this->cacheByValue[$value];

        if( !isset( $this->queryKey ) )
        {
            $this->queryKey = $this->db->prepare( "SELECT key FROM {$this->name} WHERE value = ?" );
            if( $this->queryKey === false )
            {
                if( $add === false || !self::setValue( $value ) )
                    return false;

                return self::getKey( $value );
            }
        }

        if( $this->queryKey->execute( [ $value ] ) === false )
            return false;

        $key = $this->queryKey->fetchAll();

        if( !isset( $key[0][0] ) )
        {
            if( $add === false || !self::setValue( $value ) )
                return false;

            return self::getKey( $value );
        }

        $key = $int ? intval( $key[0][0] ) : $key[0][0];
        self::setCache( $key, $value );
        return $key;
    }

    public function getValue( $key, $type = 's' )
    {
        if( isset( $this->cacheByKey[$key] ) )
            return $this->cacheByKey[$key];

        if( !isset( $this->queryValue ) )
        {
            $this->queryValue = $this->db->prepare( "SELECT value FROM {$this->name} WHERE key = ?" );
            if( $this->queryValue === false )
                return false;
        }

        if( $this->queryValue->execute( [ $key ] ) === false )
            return false;

        $value = $this->queryValue->fetchAll();

        if( !isset( $value[0][0] ) )
        {
            self::setCache( $key, false );
            return false;
        }

        $value = $value[0][0];

        if( $type === 'i' )
            $value = (int)$value;
        else if( $type === 'j' )
            $value = json_decode( $value, true, 512, JSON_BIGINT_AS_STRING );
        else if( $type === 'jz' )
            $value = json_decode( gzinflate( $value ), true, 512, JSON_BIGINT_AS_STRING );

        self::setCache( $key, $value );
        return $value;
    }

    private function setValue( $value )
    {
        if( !isset( $this->querySetValue ) )
        {
            $this->querySetValue = $this->db->prepare( "INSERT INTO {$this->name}( value ) VALUES( ? )" );
            if( $this->querySetValue === false )
                return false;
        }

        return $this->querySetValue->execute( [ $value ] );
    }

    private function executeStatement( $statement, $key, $value, $type, $unset = false )
    {
        if( $type === 'j' )
            $dbvalue = json_encode( $value );
        else if( $type === 'jz' )
            $dbvalue = gzdeflate( json_encode( $value ), 9 );
        else
            $dbvalue = $value;

        if( false === ( $result = $statement->execute( [ $key, $dbvalue ] ) ) )
            return false;

        self::setCache( $key, $value, $unset );
        return $result;
    }

    public function setKeyValue( $key, $value, $type = false )
    {
        if( !isset( $this->querySetKeyValue ) )
        {
            $this->querySetKeyValue = $this->db->prepare( "INSERT OR REPLACE INTO {$this->name}( key, value ) VALUES( :key, :value )" );
            if( $this->querySetKeyValue === false )
                return false;
        }

        return $this->executeStatement( $this->querySetKeyValue, $key, $value, $type );
    }

    public function unsetKeyValue( $key, $value, $type = false )
    {
        if( !isset( $this->queryUnsetKeyValue ) )
        {
            $this->queryUnsetKeyValue = $this->db->prepare( "DELETE FROM {$this->name} WHERE key = :key AND value = :value" );
            if( $this->queryUnsetKeyValue === false )
                return false;
        }

        return $this->executeStatement( $this->queryUnsetKeyValue, $key, $value, $type, true );
    }

    private function setCache( $key, $value, $unset = false )
    {
        if( $this->cacheSize === 0 )
            return;

        if( count( $this->cacheByKey ) >= $this->cacheSize )
            $this->resetCache();

        $this->cacheByKey[$key] = $unset ? null : $value;

        if( isset( $this->cacheByValue ) && !is_array( $value ) && $value !== false )
            $this->cacheByValue[$value] = $unset ? null : $key;
    }

    public function resetCache()
    {
        $this->cacheByKey = [];
        if( isset( $this->cacheByValue ) && count( $this->cacheByValue ) )
            $this->cacheByValue = [];
    }

    public function query( $query )
    {
        $query = $this->db->prepare( $query );
        if( !is_object( $query ) )
            return false;

        if( $query->execute() === false )
            return false;

        return $query;
    }

    public function mergeKeyValues( $kvs, $type = false )
    {
        if( !isset( $this->queryKeyValueCache ) )
        {
            $this->queryKeyValueCache = $this->db->prepare( "INSERT INTO cache.{$this->name}( key, value ) VALUES( ?, ? )" );
            if( $this->queryKeyValueCache === false )
                return false;
        }

        foreach( $kvs as $key => $value )
        {
            if( false === $this->executeStatement( $this->queryKeyValueCache, $key, $value, $type ) )
                return false;
        }
        return
        $this->db->exec( "INSERT OR REPLACE INTO {$this->name} SELECT * FROM cache.{$this->name};" ) &&
        $this->db->exec( "DELETE FROM cache.{$this->name};" );
    }
}
