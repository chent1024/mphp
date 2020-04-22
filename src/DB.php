<?php

namespace mphp;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Pagination\Paginator;

class DB
{
    public static function init($conf = 'database.connections')
    {
        $connConfig = config($conf);
        $capsule = new Capsule;
        foreach ($connConfig as $name => $config) {
            $capsule->addConnection($config, $name);
        }

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        Paginator::currentPathResolver(function () {
            return url();
        });

        Paginator::currentPageResolver(function ($pageName = 'page') {
            $page = all($pageName);
            if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int)$page >= 1) {
                return $page;
            }

            return 1;
        });
    }

    /**
     * get database connection
     * @param string $dbkey table key config in config/databases.php
     * @return LaravalDatabase   database instance
     */
    public static function connection($dbkey = null)
    {
        return Capsule::connection($dbkey);
    }

}