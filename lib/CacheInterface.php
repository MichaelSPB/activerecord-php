<?php

/**
 * @version: $Id: CacheInterface.php 382 2017-02-07 13:08:50Z VasilevMV $
 */

namespace ActiveRecord;

/**
 *
 * @author VasilevMV
 */
interface CacheInterface {

    public function flush();

    public function read($key);

    public function write($key, $value, $expire);

}
