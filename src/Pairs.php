<?php

namespace deemru;

class Pairs
{
    public function __construct( $db, $name, $writable = false, $type = 'INTEGER PRIMARY KEY|TEXT UNIQUE|0|0', $cacheSize = 1024 )
    {
        if( is_a( $db, 'PDO' ) )
        {
            $this->db = $db;
        }
        else
        {
            $this->db = new \PDO( "sqlite:$db" );
            $this->db->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING );
            $this->db->exec( 'PRAGMA temp_store = MEMORY' );
        }

        $this->name = $name;
        $this->cacheSize = $cacheSize;
        $this->cacheByKey = [];

        if( $writable )
        {
            $this->db->exec( 'PRAGMA synchronous = NORMAL; PRAGMA journal_mode = WAL; PRAGMA journal_size_limit = 1048576; PRAGMA optimize;' );
            $type = explode( '|', $type );
            $this->db->exec( "CREATE TABLE IF NOT EXISTS {$this->name}( key {$type[0]}, value {$type[1]} )" );
            if( $type[2] )
                $this->db->exec( "CREATE INDEX IF NOT EXISTS {$this->name}_key_index ON {$this->name}( key )" );
            if( $type[3] )
                $this->db->exec( "CREATE INDEX IF NOT EXISTS {$this->name}_value_index ON {$this->name}( value )" );
            if( $type[3] || false !== strpos( $type[1], 'UNIQUE' ) )
                $this->cacheByValue = [];
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
            $this->queryKey = $this->db->prepare( "SELECT key FROM {$this->name} WHERE value = :value" );
            if( $this->queryKey === false )
            {
                if( $add === false || !self::setValue( $value ) )
                    return false;

                return self::getKey( $value );
            }
        }

        if( $this->queryKey->execute( [ 'value' => $value ] ) === false )
            return false;

        $key = $this->queryKey->fetchAll( \PDO::FETCH_ASSOC );

        if( !isset( $key[0]['key'] ) )
        {
            if( $add === false || !self::setValue( $value ) )
                return false;

            return self::getKey( $value );
        }

        $key = $int ? intval( $key[0]['key'] ) : $key[0]['key'];
        self::setCache( $key, $value );
        return $key;
    }

    public function getValue( $key, $type = 's' )
    {
        if( isset( $this->cacheByKey[$key] ) )
            return $this->cacheByKey[$key];

        if( !isset( $this->queryValue ) )
        {
            $this->queryValue = $this->db->prepare( "SELECT value FROM {$this->name} WHERE key = :key" );
            if( $this->queryValue === false )
                return false;
        }

        if( $this->queryValue->execute( [ 'key' => $key ] ) === false )
            return false;

        $value = $this->queryValue->fetchAll( \PDO::FETCH_ASSOC );

        if( !isset( $value[0]['value'] ) )
        {
            self::setCache( $key, false );
            return false;
        }

        $value = $value[0]['value'];

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
            $this->querySetValue = $this->db->prepare( "INSERT INTO {$this->name}( value ) VALUES( :value )" );
            if( $this->querySetValue === false )
                return false;
        }

        return $this->querySetValue->execute( [ 'value' => $value ] );
    }

    public function setKeyValue( $key, $value, $type = false )
    {
        self::setCache( $key, $value );

        if( !isset( $this->querySetKeyValue ) )
        {
            $this->querySetKeyValue = $this->db->prepare( "INSERT OR REPLACE INTO {$this->name}( key, value ) VALUES( :key, :value )" );
            if( $this->querySetKeyValue === false )
                return false;
        }

        if( $type === 'j' )
            $value = json_encode( $value );
        else if( $type === 'jz' )
            $value = gzdeflate( json_encode( $value ), 9 );

        return $this->querySetKeyValue->execute( [ 'key' => $key, 'value' => $value ] );
    }

    private function setCache( $key, $value )
    {
        if( count( $this->cacheByKey ) >= $this->cacheSize )
            $this->resetCache();

        $this->cacheByKey[$key] = $value;

        if( isset( $this->cacheByValue ) && !is_array( $value ) && $value !== false )
            $this->cacheByValue[$value] = $key;
    }

    private function resetCache()
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
}
