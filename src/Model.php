<?php
namespace mphp;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Model extends Eloquent {
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->initDb();
    }

    protected function initDb()
    {
        static $initDb = false;
        if(!$initDb){
            DB::init();
            $initDb = true;
        }
    }
}
