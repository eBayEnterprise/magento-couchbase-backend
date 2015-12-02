<?php
/**
 * Copyright (c) 2015-2016 eBay Enterprise, Inc.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magento.com for more information.
 *
 * @category    EbayEnterprise
 * @package     EbayEnterprise_Cache
 * @copyright   Copyright (c) 2015-2016 Ebay Enterprise, Inc. (http://www.ebayenterprise.com)
 * @author      Robert Krule <rkrule@ebay.com>
 * @createdate  December 2, 2015
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Couchbase cache backend
 */
class EbayEnterprise_Cache_Backend_Couchbase extends Zend_Cache_Backend implements Zend_Cache_Backend_ExtendedInterface
{ 
    /**
     * Tag # Chunk
     *
     */
     protected $_tagSize = 100;

    /**
      * Cache Key # Chunk
      *
      */
      protected $_keySize = 100;

    /**
     * Tag Expiration TTL
     *
     * @var int tag TTL
     */
    protected $_tagExpiry;

    /**
     * Couchbase Cluster Instance
     *
     * @var CouchbaseCluster Object
     */
    protected $_cluster;

    /**
     * Couchbase Bucket Instance
     *
     * @var CouchbaseBucket Object
     */
    protected $_bucket;

    /**
     * Couchbase Bucket Session Instance
     *
     * @var CouchbaseBucket Object
     */
    protected $_bucketSession;

    /**
     * Couchbase Bucket Tag Instance
     *
     * @var CouchbaseBucket Object
     */
    protected $_bucketTags;

    /**
     * Couchbase Bucket Tag Instance
     *
     * @var CouchbaseBucket Object
     */
    protected $_bucketFPC;

    /**
     * Roids Catalog Search PageCache EXT Installed
     *
     * @var boolean
     */ 
     protected $_roidsCatalogSearch;

     /**
      * Default Timeut
      */
     protected $_defaultTimeout = 86400;

    /**
     * Constructor
     *
     * @param array $options associative array of options
     */
    public function __construct($options = array())
    {
        parent::__construct($options);
        $this->_options = $options;

        $this->_cluster = $this->getConnection();

        $this->_bucket = $this->getBucket();
	$this->_bucket->__set('operationTimeout', 2000000); // 100000 = 1 second
	$this->_bucket->__set('viewTimeout', 60000000); // 60 second timeout for views

        $this->_bucketSession = $this->getBucketSession();
        $this->_bucketSession->__set('operationTimeout', 2000000); // 100000 = 1 second
	$this->_bucketSession->__set('viewTimeout', 60000000);

        $this->_bucketTags = $this->getBucketTags();
        $this->_bucketTags->__set('operationTimeout', 2000000); // 100000 = 1 second
	$this->_bucketTags->__set('viewTimeout', 60000000);

        if (isset($this->_options['consoleTagExpiry'])) {
            $this->_tagExpiry = (int)$this->_options['consoleTagExpiry'];
        } else {
            $this->_tagExpiry = 3600;
        }
    }

   /**
     * getCouchbaseConnection
     * @return CouchCluster
     */

    public function getConnection()
    {
        if ( isset($this->_options['consoleConnectionString']) && isset($this->_options['consoleUser']) && isset($this->_options['consolePassword']))
	{
        	$connection_string = (string)$this->_options['consoleConnectionString'];
        	$user = (string)$this->_options['consoleUser'];
        	$password = (string)$this->_options['consolePassword'];

	        return new CouchbaseCluster($connection_string, $user, $password );
	} else {
            Zend_Cache::throwException('Couchbase Options: consoleConnectionString, consoleUser, consolePassword should be declared!');
        }
    }

    public function getBucket()
    {
        if (isset($this->_options['consoleFPCBucket'])) {
            $bucket_name = (string)$this->_options['consoleFPCBucket'];
        }

        if (isset($this->_options['consoleGeneralCacheBucket'])) {
            $bucket_name = (string)$this->_options['consoleGeneralCacheBucket'];
	    $this->_bucketFPC = $this->_cluster->openBucket((string)Mage::getConfig()->getNode('global/full_page_cache/backend_options')->consoleFPCBucket);
        }

	if ( !isset($this->_options['consoleFPCBucket']) && !isset($this->_options['consoleGeneralCacheBucket']))
	{
		Zend_Cache::throwException('Couchbase Bucket Option: consoleFPCBucket or consoleGeneralCacheBucket must be declared!');
	}

        return $this->_cluster->openBucket($bucket_name);
    }

    public function getBucketSession()
    {
	if ( !isset($this->_options['consoleSessionBucket'])) 
	{
        	Zend_Cache::throwException('Couchbase Session Bucket: consoleSessionBucket must be declared!');
	}

        $bucket_name = (string)$this->_options['consoleSessionBucket'];

	$preq = explode('?', (string)$this->_options['consoleConnectionString'])[0];
        $new_conn_bucket = $preq . '/' . $bucket_name . '?config_cache=/tmp/phpcb_cache_session&http_poolsize=10';

        return new CouchbaseBucket($new_conn_bucket, $bucket_name, '');
    }

    public function getBucketTags()
    {
	if ( !isset($this->_options['consoleTagBucket']))
        {
                Zend_Cache::throwException('Couchbase Tag Bucket: consoleTagBucket must be declared!');
        }

        $bucket_name = (string)$this->_options['consoleTagBucket'];

	$preq = explode('?', (string)$this->_options['consoleConnectionString'])[0];
        $new_conn_bucket = $preq . '/' . $bucket_name . '?config_cache=/tmp/phpcb_cache_tags&http_poolsize=10';

        return new CouchbaseBucket($new_conn_bucket, $bucket_name, '');
    }

    /**
     * Test if a cache is available for the given id and (if yes) return it (false else)
     *
     * Note : return value is always "string" (unserialization is done by the core not by the backend)
     *
     * @param  string $id Cache id
     * @param  boolean $doNotTestCacheValidity If set to true, the cache validity won't be tested
     * @return string|false cached datas
     */
    public function load($id, $doNotTestCacheValidity = false)
    {
        $result = null;
	$resultProc = null;

        try {
            $result = $this->_bucket->get($id)->value;
        } catch (CouchbaseException $e) {
	    	if( strpos($e->getMessage(), "The key does not exist on the server") === false )
		{
			Mage::log($e->getMessage(), Zend_Log::ERR);
		}

		$result = false;
        };

    	return $result;
     }

    /**
     * Test if a cache is available or not (for the given id)
     *
     * @param  string $id cache id
     * @return mixed|false (a cache is not available) or payload available cache record
     */
    public function test($id)
    {
    	$result = null;
        $resultProc = null;

        try {
            $result = $this->_bucket->get($id)->value;
        } catch (CouchbaseException $e) {
                if( strpos($e->getMessage(), "The key does not exist on the server") === false )
                {
                        Mage::log($e->getMessage(), Zend_Log::ERR);
                }

                $result = false;
        };

        return $result;
    }

    /**
     * Save some string datas into a cache record
     *
     * Note : $data is always "string" (serialization is done by the
     * core not by the backend)
     *
     * @param  string $data Datas to cache
     * @param  string $id Cache id
     * @param  array $tags Array of strings, the cache record will be tagged by each string entry
     * @param  int $specificLifetime If != false, set a specific lifetime for this cache record (null => infinite lifetime)
     * @return boolean true if no problem
     */
    public function save($data, $id, $tags = array(), $specificLifetime = false)
    {
	$status = true;

	if ( isset($tags['tag'])  && isset($tags['cache_id'], $tags) )
	{
		$temp = array();
		$temp = array("tag" => $tags['tag'], "cache_id" => $tags['cache_id']);

		if ( empty($tags) || count($tags) == 0 ) {
            		$status = false;
			return $status;
        	}

        	try
        	{
                	$this->_bucketTags->upsert(uniqid(), $temp);
                	$status = true;
        	} catch (CouchbaseException $e) {
      			if ( strpos("The key already exists in the server", $e->getMessage()) !== false )
	                {
                        	$status = false;
                	} else {
                        	Mage::log($e->getMessage(), Zend_Log::ERR);
                        	$status = false;
                	}
        	};

        	return $status;
	}

        $lifetime = $this->getLifetime($specificLifetime);

        if (!empty($lifetime)) {
	    try {
                $this->_bucket->upsert($id, $data, array('expiry' => (int)$lifetime));
  	    } catch (CouchbaseException $e) {
               	Mage::log($e->getMessage(), Zend_Log::ERR);
		$status = false;
            };
        } else {
	    try {
		if (isset($this->_options['consoleFPCBucket']))
                {
                        $this->_bucket->upsert($id, $data, array('expiry' => $this->_defaultTimeout));
                } else {
                        $this->_bucket->upsert($id, $data );
                }
	    } catch (CouchbaseException $e) {
                Mage::log($e->getMessage(), Zend_Log::ERR);
                $status = false;
	    };
        }

	// Save tags now....
	
	if (empty($tags) || count($tags) == 0 ) {
            return $status;
        }

	if (!is_array($tags)) {
            $tags = array($tags);
        }

        foreach ($tags as $tag) {
	    try
	    {
            	$this->_bucketTags->upsert(uniqid(), array("tag" => $tag, "cache_id" => $id), array('expiry' => $this->_tagExpiry));
	    } catch (CouchbaseException $e) {
                Mage::log($e->getMessage(), Zend_Log::ERR);
                $status = false;
	    };
        }

	return $status;
    }

    /**
     * Remove a cache record
     *
     * @param  string $id Cache id(s) from all other buckets
     * @return boolean True if no problem
     */
    public function remove($id)
    {
	$status = true;
	
        $size_key_array = count($id);
        $size_key_ceil = 0;     

        if( $size_key_array > $this->_keySize )
        {
                $size_key_ceil = ceil( $size_key_array / $this->_keySize );
        }

        if ( $size_key_ceil === 0 )
        {
		$reindexed_ids = null;

		if ( is_array($id) )
		{
			$reindexed_ids = array_values($id);
		} else {
			$reindexed_ids[] = $id;
		}

        	 // Cache Key Removal...
        	if ( !empty($id) || count($id) > 0 ) {
                	try {
				if ( $reindexed_ids === null )
				{
                    			$this->_bucket->remove($id);

                    			if ( is_object($this->_bucketFPC) )
                    			{
                        			$this->_bucketFPC->remove($id);
                    			}
				} else { 
					$this->_bucket->remove($reindexed_ids);

                                        if ( is_object($this->_bucketFPC) )
                                        {
                                                $this->_bucketFPC->remove($reindexed_ids);
                                        }
				}
                	} catch (CouchbaseException $e) {
                	    if ( strpos($e->getMessage(), "The key does not exist on the server") === false )
                	    {
                        	Mage::log($e->getMessage(), Zend_Log::ERR);
			    }

			    $status = false;
                	};
		}
	} else { 
                $reindexed_ids = array_values($id);
		
                for ( $j = 0; $j < $size_key_ceil; $j++ )
                {
                        $temp_ids = array();

                        for( $i = 0; $i < $this->_keySize; $i++ )
                        {
                                $iterator = $i + ($j * $this->_keySize );

                                if ( $iterator < count($reindexed_ids))
                                {
                                        $temp_id[] = $reindexed_ids[$iterator];
                                }
                        }

			try {
                        	$this->_bucket->remove($temp_id);

                        	if ( is_object($this->_bucketFPC) )
                        	{
                        		$this->_bucketFPC->remove($temp_id);
                        	}
			} catch (CouchbaseException $e) {
                  	  	if ( strpos($e->getMessage(), "The key does not exist on the server") === false )
                  	  	{
                  	  	    Mage::log($e->getMessage(), Zend_Log::ERR);
				}
			
				$status = false;
                	};
                 }
	}	

	return $status;
    }

    /**
     * Clean some cache records
     *
     * Available modes are :
     * Zend_Cache::CLEANING_MODE_ALL (default)    => remove all cache entries ($tags is not used)
     * Zend_Cache::CLEANING_MODE_OLD              => remove too old cache entries ($tags is not used)
     * Zend_Cache::CLEANING_MODE_MATCHING_TAG     => remove cache entries matching all given tags
     *                                               ($tags can be an array of strings or a single string)
     * Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG => remove cache entries not {matching one of the given tags}
     *                                               ($tags can be an array of strings or a single string)
     * Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG => remove cache entries matching any given tags
     *                                               ($tags can be an array of strings or a single string)
     *
     * @param  string $mode Clean mode
     * @param  array $tags Array of tags
     * @return boolean true if no problem
     */
    public function clean($mode = Zend_Cache::CLEANING_MODE_ALL, $tags = array())
    {
	$result = true;

        switch ($mode) {
            case Zend_Cache::CLEANING_MODE_ALL:
                $this->_bucketTags->manager()->flush();
                $this->_bucket->manager()->flush();
                $this->_bucketSession->manager()->flush();
                $result = true;
                break;
            case Zend_Cache::CLEANING_MODE_OLD:
                $result = true;
                break;
            case Zend_Cache::CLEANING_MODE_MATCHING_TAG:
                if (!empty($tags)) {

                    $ids = array(); // no need for this line
                    $ids = $this->getIdsMatchingTags($tags);

                    if (count($ids) > 0) {
                        $cacheremove = $result = $this->remove($ids);
                        $tagremove = $this->cleanTags($ids);
			$cacheremove = true;
			$tagremove = true;

			$result = $cacheremove & $tagremove;
                    } else {
			$result = true;
		    }
                } else {
			$result = true;
		}

                break;
            case Zend_Cache::CLEANING_MODE_NOT_MATCHING_TAG:
                if (!empty($tags)) {
                    $diff_ids = array();
                    $diff_ids = $this->getIdsNotMatchingTags($tags);

                    if (count($diff_ids) > 0) {
                        $result = $this->remove($diff_ids);
			$result = true;
                        // No point in removing tags here, leave the existing relationships in tact
                    } else {
			$result = true;
		    }
                } else {
			$result = true;
		}

                break;
            case Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG:
		if (!empty($tags)) {
                    $ids = array();
                    $ids = $this->getIdsMatchingAnyTags($tags);

		    // Even if there is no tag resolution, it could have still been used in a search previously.
		    //$searchremove = $this->purgeCatalogSearchKeys($tags);
		    $searchremove = true;

                    if (count($ids) > 0) {
                        $cacheremove = $this->remove($ids);
			$cacheremove = true;
                        $tagremove = $this->cleanTags($ids);
			$tagremove = true;
			$result = $cacheremove & $tagremove & $searchremove;
                    } else {
			$result = true;
		    }
                } else {
                	$result = true;
		}

                break;
            default:
                Zend_Cache::throwException('Invalid mode for clean() method');
		$result = false;
                break;
        }

        return $result;
    }

    /**
     * Return an array of stored cache ids
     *
     * @return array array of stored cache ids (string)
     */
    public function getIds()
    {
        $cache_ids = array();

	$designDoc = "cache";
	$designView = "all_tags";

	$docs = $this->_bucketTags->manager()->getDesignDocuments();

        if(isset($docs[$designDoc]))
        {
		$query = CouchbaseViewQuery::from($designDoc, $designView);
		$result = $this->_bucketTags->query($query);

        	foreach ($result['rows'] as $row) {
        	    array_push($cache_ids, (string)$row['value']);
        	}
	}

        return $cache_ids;
    }

    /**
     * Return an array of stored tags
     *
     * @return array array of stored tags (string)
     */
    public function getTags()
    {
        $cache_ids = array();

	$designDoc = "cache";
        $designView = "all_tags";

	$docs = $this->_bucketTags->manager()->getDesignDocuments();

        if(isset($docs[$designDoc]))
        {
        	$query = CouchbaseViewQuery::from($designDoc, $designView);
        	$result = $this->_bucketTags->query($query);

        	foreach ($result['rows'] as $row) {
        	    array_push($cache_ids, (string)$row['key']);
        	}
	}

        return array_unique($cache_ids);
    }

    /**
     * Return an array of stored cache ids which match ALL given tags
     *
     * In case of multiple tags, a logical AND is made between tags
     *
     * @param array $tags array of tags
     * @return array array of matching cache ids (string)
     */
    public function getIdsMatchingTags($tags = array())
    {
        if (!empty($tags)) {
            $intersectArray = array();

            // Find a view for each tag, push its result to an array, then take an intersection of each result array

            foreach ($tags as $tag) {
	        $designDoc = "cache";
        	$designView = "all_tags";

		$docs = $this->_bucketTags->manager()->getDesignDocuments();

                if(isset($docs[$designDoc]))
                {
        		$query = CouchbaseViewQuery::from($designDoc, $designView)->key($tag);
        		$result = $this->_bucketTags->query($query);

                	$cache_ids = array();
                	$cache_ids_uniq = array();
	
        	        foreach ($result['rows'] as $row) {
                	    array_push($cache_ids, $row['value']);
                	}

                	$cache_ids_uniq = array_unique($cache_ids);

                	array_push($intersectArray, $cache_ids_uniq);

            		// Now take the intersection of the resultant arrays

            		if (count($intersectArray) == 1) {
            		    return $intersectArray[0];
            		} else {
                		return call_user_func_array('array_intersect', $intersectArray);
            		}
		} else {
			return array();
		}
	     }
        } else {
            return array();
        }
    }

    /**
     * Return an array of stored cache ids which don't match given tags
     *
     * In case of multiple tags, a logical OR is made between tags
     *
     * @param array $tags array of tags
     * @return array array of not matching cache ids (string)
     */
    public function getIdsNotMatchingTags($tags = array())
    {
        return array_diff($this->getIds(), $this->getIdsMatchingAnyTags($tags));
    }

    /**
     * Return an array of stored cache ids which match any given tags
     *
     * In case of multiple tags, a logical AND is made between tags
     *
     * @param array $tags array of tags
     * @return array array of any matching cache ids (string)
     */
    public function getIdsMatchingAnyTags($tags = array())
    {
        $cache_ids = array();
	$size_tag_array = count($tags);
	$size_tag_ceil = 0;	

	if( $size_tag_array > $this->_tagSize )
	{
		$size_tag_ceil = ceil( $size_tag_array / $this->_tagSize );
	}

	if ( $size_tag_ceil === 0 )
	{
        	if (count($tags) > 0) {
		    $reindexed_tags = array_values($tags);

		    $designDoc = "cache";
                    $designView = "all_tags";

		    $docs = $this->_bucketTags->manager()->getDesignDocuments();

		    if(isset($docs[$designDoc]))
		    {
                    	  $query = CouchbaseViewQuery::from($designDoc, $designView)->keys($reindexed_tags);
			  $result = $this->_bucketTags->query($query);

                          foreach ($result['rows'] as $row) {
                        	array_push($cache_ids, $row['value']);
                    	  }
		    }
		}
        } else { 
		$reindexed_tags = array_values($tags);

        	for ( $j = 0; $j < $size_tag_ceil; $j++ )
		{
			$temp_tag = array();

			for( $i = 0; $i < $this->_tagSize; $i++ )
			{
				$iterator = $i + ($j * $this->_tagSize );

				if ( $iterator < count($tags))
				{
					$temp_tag[] = $reindexed_tags[$iterator];
				}
			}
			
			$designDoc = "cache";
                    	$designView = "all_tags";

                    	$docs = $this->_bucketTags->manager()->getDesignDocuments();

                    	if(isset($docs[$designDoc]))
                    	{
                        	$query = CouchbaseViewQuery::from($designDoc, $designView)->keys($temp_tag);
                        	$result = $this->_bucketTags->query($query);

                        	foreach ($result['rows'] as $row) {
                                	array_push($cache_ids, $row['value']);
                          	}
                    	}
		} 
	}

        return array_unique($cache_ids);
    }

    /**
     * Return the filling percentage of the backend storage
     *
     * @return int integer between 0 and 100
     */
    public function getFillingPercentage()
    {
        return 1;
    }

    /**
     * Return an array of metadatas for the given cache id
     *
     * The array must include these keys :
     * - expire : the expire timestamp
     * - tags : a string array of tags
     * - mtime : timestamp of last modification time
     *
     * @param string $id cache id
     * @return array array of metadatas (false if the cache id is not found)
     */
    public function getMetadatas($id)
    {
        return false;
    }

    /**
     * Give (if possible) an extra lifetime to the given cache id
     *
     * @param string $id cache id
     * @param int $extraLifetime
     * @return boolean true if ok
     */
    public function touch($id, $extraLifetime)
    {
        $result = false;

        try {
            $this->_bucket->touch($id, $extraLifetime);
            $result = true;
        } catch (CouchbaseException $e) {
	    if ( strpos($e->getMessage(), "The key does not exist on the server") === false )
            {
            	Mage::log($e->getMessage(), Zend_Log::ERR);
            }

	    $result = false;
	};

        return $result;
    }

    /**
     * Return an associative array of capabilities (booleans) of the backend
     *
     * The array must include these keys :
     * - automatic_cleaning (is automating cleaning necessary)
     * - tags (are tags supported)
     * - expired_read (is it possible to read expired cache records
     *                 (for doNotTestCacheValidity option for example))
     * - priority does the backend deal with priority when saving
     * - infinite_lifetime (is infinite lifetime can work with this backend)
     * - get_list (is it possible to get the list of cache ids and the complete list of tags)
     *
     * @return array associative of with capabilities
     */
    public function getCapabilities()
    {
        return array(
            'automatic_cleaning' => true,
            'tags' => true,
            'expired_read' => true,
            'priority' => false,
            'infinite_lifetime' => true,
            'get_list' => true
        );
    }

    /**
     * Clean cache tags by cache id
     *
     * @param array $ids
     * @return bool
     */
    public function cleanTags($ids = array())
    {
	$status = true;
	$cache_key_ids = array();
        $size_key_array = count($ids);
        $size_key_ceil = 0;

	// Chunk up cache key tag removal into property

        if( $size_key_array > $this->_keySize )
        {
                $size_key_ceil = ceil( $size_key_array / $this->_keySize );
        }

	Mage::Log("In cleanTags... Purging: ". print_r($ids, true));

	//Reindex array
        $ids_reindexed_array = array_values($ids);

        if ( $size_key_ceil == 0 )
        {
	        $designDoc = "cache";
                $designView = "all_tag_values";

                $docs = $this->_bucketTags->manager()->getDesignDocuments();

                if(isset($docs[$designDoc]))
                {
                	$query = CouchbaseViewQuery::from($designDoc, $designView)->keys($ids_reindexed_array);
                	$result = $this->_bucketTags->query($query);
		
			$tag_ids = array();

        		foreach ($result['rows'] as $row) {
				if ( strpos($row['value'], 'CATALOG_SEARCH') === false )
                        	{ 
					array_push($tag_ids, $row['id']);
				}
			}

			if ( count($tag_ids) > 0 )
			{
				try
				{
		    			$this->_bucketTags->remove($tag_ids);
            			} catch (CouchbaseException $e) {
            		    		if ( strpos($e->getMessage(), "The key does not exist on the server" ) === false )
                	    		{
                	        		Mage::log($e->getMessage(), Zend_Log::ERR);
			   		}

					$status = false;
				};
        		}
		}
    	} else {
		for ( $j = 0; $j < $size_key_ceil; $j++ )
                {
                        $temp_key = array();

                        for( $i = 0; $i < $this->_keySize; $i++ )
                        {
                                $iterator = $i + ($j * $this->_keySize );

                                if ( $iterator < $size_key_array)
                                {
                                        $temp_key[] = $ids_reindexed_array[$iterator];
                                }
			
				$designDoc = "cache";
		                $designView = "all_tag_values";

                		$docs = $this->_bucketTags->manager()->getDesignDocuments();

                		if(isset($docs[$designDoc]))
                		{
                        		$query = CouchbaseViewQuery::from($designDoc, $designView)->keys($temp_key);
                        		$result = $this->_bucketTags->query($query);

                        		$tag_ids = array();

                        		foreach ($result['rows'] as $row) {
                                		if ( strpos($row['value'], 'CATALOG_SEARCH') === false )
                                		{
                                        		array_push($tag_ids, $row['id']);
                                		}
                        		}

                			if ( count($tag_ids) > 0 )
                			{
                        			try
                        			{
                                			$this->_bucketTags->remove($tag_ids);
                        			} catch (CouchbaseException $e) {
                            				if ( strpos($e->getMessage(), "The key does not exist on the server") === false )
                            				{
                                				Mage::log($e->getMessage(), Zend_Log::ERR);
                            				}

							$status = false;
                        			};
                			}
				}
			}
		}
	}
	
	return $status;
  }

  /**
    * Purge CatalogSearch Cache Keys
    *
    * @param array tags
    * @return array of cache key ids (couchbase ids)
    */
    public function purgeCatalogSearchKeys($tags = array())
    {
	$status = true;
	$cache_key_ids = array();
        $size_tag_array = count($tags);
        $size_tag_ceil = 0;

        if( $size_tag_array > $this->_tagSize )
        {
                $size_tag_ceil = ceil( $size_tag_array / $this->_tagSize );
        }

        if ( $size_tag_ceil === 0 )
        {
                if (count($tags) > 0) 
		{
                	 //Reindex array
                	$tags_reindexed_array = array_values($tags);
	
	                $designDoc = "cache";
                        $designView = "all_search_tags";

                        $docs = $this->_bucketTags->manager()->getDesignDocuments();

                        if(isset($docs[$designDoc]))
                        {
                        	$query = CouchbaseViewQuery::from($designDoc, $designView)->keys($tags);
                        	$result = $this->_bucketTags->query($query);
			
				$cache_key_ids = array();

                		foreach ( $tags_reindexed_array as $tag )
                		{
                        		foreach ($result['rows'] as $row)
                        		{
                                		if ( strpos($row['key'], $tag) !== false )
                                		{
                                	        	$search_cache_tag = $row['value'];
                                	        	$search_cache_key = str_replace( '_CATALOG_SEARCH', '', $search_cache_tag);

                                	        	$cache_key_ids[] = $search_cache_key;
                                		}
                        		}
                		}
			}
		}
        } else {
		$tags_reindexed_array = array_values($tags);

                for ( $j = 0; $j < $size_tag_ceil; $j++ )
                {
                        $temp_tag = array();

                        for( $i = 0; $i < $this->_tagSize; $i++ )
                        {
                                $iterator = $i + ($j * $this->_tagSize );

                                if ( $iterator < count($tags))
                                {
                                        $temp_tag[] = $tags_reindexed_array[$iterator];
                                }

				$designDoc = "cache";
	                        $designView = "all_search_tags";
        	 
                                $docs = $this->_bucketTags->manager()->getDesignDocuments();

                                if(isset($docs[$designDoc]))
                                {
		                	$query = CouchbaseViewQuery::from($designDoc, $designView)->keys($temp_tag);
                	        	$result = $this->_bucketTags->query($query);

					$cache_key_ids = array();

                        		foreach ( $tags_reindexed_array as $tag )
                        		{
                        	        	foreach ($result['rows'] as $row)
                        	        	{
                        	                	if ( strpos($row['key'], $tag) !== false )
                        	                	{
                                	                	$search_cache_tag = $row['value'];
                                	                	$search_cache_key = str_replace( '_CATALOG_SEARCH', '', $search_cache_tag);
	
        	                        	                $cache_key_ids[] = $search_cache_key;
                	                	        }
                        	        	}
                        		}
                        	}
                	}
		}
	}

	if ( count($cache_key_ids) > 0 )
        {
            	$removeFPC = $this->_bucket->remove($cache_key_ids);
                $removeTags = $this->cleanTags($cache_key_ids);

		$status = $removeFPC & $removeTags;
	}

	return $status;
    }
}