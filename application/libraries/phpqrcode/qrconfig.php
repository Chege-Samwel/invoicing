<?php
/*
 * PHP QR Code encoder
 *
 * Config file, feel free to modify
 */
     
    /*
     * Off, deliberately (Adomin).
     *
     * With the cache on, the encoder writes each mask and format template to
     * disk as a gzcompress()ed blob. That makes two functions mandatory which
     * this host does not guarantee: gzcompress() (zlib) and a writable cache
     * directory - and a read-only container has neither. With it off the same
     * frames are generated in pure PHP, which for a 29x29 code is microseconds
     * and, more to the point, cannot fail on a host that has no zlib.
     */
    define('QR_CACHEABLE', false);                                                              // use cache - more disk reads but less CPU power, masks and format templates are stored there
    define('QR_CACHE_DIR', dirname(__FILE__).DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR);  // used when QR_CACHEABLE === true
    define('QR_LOG_DIR', dirname(__FILE__).DIRECTORY_SEPARATOR);                                // default error logs dir   
    
    define('QR_FIND_BEST_MASK', true);                                                          // if true, estimates best mask (spec. default, but extremally slow; set to false to significant performance boost but (propably) worst quality code
    define('QR_FIND_FROM_RANDOM', false);                                                       // if false, checks all masks available, otherwise value tells count of masks need to be checked, mask id are got randomly
    define('QR_DEFAULT_MASK', 2);                                                               // when QR_FIND_BEST_MASK === false
                                                  
    define('QR_PNG_MAXIMUM_SIZE',  1024);                                                       // maximum allowed png image width (in pixels), tune to make sure GD and PHP can handle such big images
                                                  