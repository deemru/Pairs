<?php

require __DIR__ . '/../vendor/autoload.php';
use deemru\Pairs;

$pairs = new Pairs( __DIR__ . '/storage.sqlite', 'pairs', 2 );

$key = 1;
$value = 'Hello, World!';
$pairs->setKeyValue( $key, $value );

if( $pairs->getKey( $value ) !== $key ||
    $pairs->getValue( $key ) !== $value )
    exit( 1 );

if( !$pairs->unsetKeyValue( $key, $value ) ||
    $pairs->getKey( $value ) !== false ||
    $pairs->getValue( $key ) !== false )
    exit( 1 );

class tester
{
    private $successful = 0;
    private $failed = 0;
    private $depth = 0;
    private $info = [];
    private $start = [];

    public function pretest( $info )
    {
        $this->info[$this->depth] = $info;
        $this->start[$this->depth] = microtime( true );
        if( !isset( $this->init ) )
            $this->init = $this->start[$this->depth];
        $this->depth++;
    }

    private function ms( $start )
    {
        $ms = ( microtime( true ) - $start ) * 1000;
        $ms = $ms > 100 ? round( $ms ) : $ms;
        $ms = sprintf( $ms > 10 ? ( $ms > 100 ? '%.00f' : '%.01f' ) : '%.02f', $ms );
        return $ms;
    }

    public function test( $cond )
    {
        $this->depth--;
        $ms = $this->ms( $this->start[$this->depth] );
        echo ( $cond ? 'SUCCESS: ' : 'ERROR:   ' ) . "{$this->info[$this->depth]} ($ms ms)\n";
        $cond ? $this->successful++ : $this->failed++;
    }

    public function finish()
    {
        $total = $this->successful + $this->failed;
        $ms = $this->ms( $this->init );
        echo "  TOTAL: {$this->successful}/$total ($ms ms)\n";
        sleep( 3 );

        if( $this->failed > 0 )
            exit( 1 );
    }
}

echo "   TEST: Pairs\n";
$t = new tester();

for( $iters = 50000; $iters >= 100; $iters = (int)( $iters / 10 ) )
{
    $data = [];
    $t->pretest( "fill PHP data ($iters)" );
    {
        for( $i = 0; $i < $iters; $i++ )
        {
            $value = sha1( $value );
            $data[] = $value;
        }
        
        $t->test( count( $data ) === $iters );
    }

    $pairs->reset();
    $t->pretest( "data to Pairs ($iters) (write) (simple)" );
    {
        foreach( $data as $key => $value )
        {
            $result = $pairs->setKeyValue( $key, $value );
            if( $result === false )
                break;
        }
    }
    $t->test( $result !== false );
    $t->pretest( "data to Pairs ($iters) (read)  (simple)" );
    {
        if( $result !== false )
        foreach( $data as $key => $value )
        {
            $result = $pairs->getKey( $value );
            if( $result !== $key )
            {
                $result = false;
                break;
            }
        }

        if( $result !== false )
        foreach( $data as $key => $value )
        {
            $result = $pairs->getValue( $key );
            if( $result !== $value )
            {
                $result = false;
                break;
            }
        }

        $t->test( $result !== false );
    }

    $pairs->reset();
    $t->pretest( "data to Pairs ($iters) (write) (commit)" );
    {
        $pairs->begin();
        foreach( $data as $key => $value )
        {
            $result = $pairs->setKeyValue( $key, $value );
            if( $result === false )
                break;
        }
        $pairs->commit();
    } 
    $t->test( $result !== false );
    $t->pretest( "data to Pairs ($iters) (read)  (commit)" );
    {
        if( $result !== false )
        foreach( $data as $key => $value )
        {
            $result = $pairs->getKey( $value );
            if( $result !== $key )
            {
                $result = false;
                break;
            }
        }

        if( $result !== false )
        foreach( $data as $key => $value )
        {
            $result = $pairs->getValue( $key );
            if( $result !== $value )
            {
                $result = false;
                break;
            }
        }

        $t->test( $result !== false );
    }

    $pairs->reset();
    $t->pretest( "data to Pairs ($iters) (write) (merge) " );
    {
        $pairs->begin();
        $result = $pairs->mergeKeyValues( $data );      
        $pairs->commit();
    } 
    $t->test( $result !== false );
    $t->pretest( "data to Pairs ($iters) (read)  (merge) " );
    {
        if( $result !== false )
        foreach( $data as $key => $value )
        {
            $result = $pairs->getKey( $value );
            if( $result !== $key )
            {
                $result = false;
                break;
            }
        }

        if( $result !== false )
        foreach( $data as $key => $value )
        {
            $result = $pairs->getValue( $key );
            if( $result !== $value )
            {
                $result = false;
                break;
            }
        }

        $t->test( $result !== false );
    }
}

$t->finish();
