<?php

require __DIR__ . '/../vendor/autoload.php';
use deemru\Pairs;

$pairs = new Pairs( __DIR__ . '/storage.sqlite', 'pairs', true );

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

for( $iters = 10000; $iters > 100; $iters = (int)( $iters / 2 ) )
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

    $t->pretest( "data to Pairs ($iters) (simple)" );
    {
        $pairs->reset();
        foreach( $data as $key => $value )
        {
            $result = $pairs->setKeyValue( $key, $value );
            if( $result === false )
                break;
        }

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

    $t->pretest( "data to Pairs ($iters) (commit)" );
    {
        $pairs->reset();
        $pairs->begin();
        foreach( $data as $key => $value )
        {
            $result = $pairs->setKeyValue( $key, $value );
            if( $result === false )
                break;
        }
        $pairs->commit();

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
