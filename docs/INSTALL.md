![eBay Enterprise](static/logo-vert.png)

**Zend Framework 1 Couchbase Cache Backend Extended Interface**
# Installation and Configuration Guide

The intended audience for this guide is Magento system integrators and core development. You should review the [Zend Framework Couchbase Backend Driver Overview](README.md) before attempting to install and configure the driver.

Knowledge of Magento installation and configuration, [PHP Composer](https://getcomposer.org/) and Magento XML Configuration is assumed in this document.

## Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Local XML Configuration](#local-xml-configuration)

## Requirements

### System Requirements

- Magento Enterprise Edition 1.14.2 ([system requirements](http://magento.com/resources/system-requirements))
- [Magento Composer Installer](https://github.com/Cotya/magento-composer-installer)
- [libcouchbase - 2.5.X](https://github.com/couchbase/libcouchbase)
	Ensure the following dependencies are installed as well:
		-[libevent](http://libevent.org/)
		-[openssl libraries](https://www.openssl.org/docs/manmaster/crypto/crypto.html)
		-[CMake >= 2.8.9](https://cmake.org/)
- [PHP libcouchbase extension >= 2.1.0](https://github.com/couchbase/php-couchbase)
- [PHP 5.6](http://php.net)
- [Couchbase Server >= 3.0.X](http://www.couchbase.com/)

## Installation

1. Copy all of the files from the src/app abd src/lib directories to DocRoot of the Magento installation, example: /var/www/magento
2. Examine the src/app/etc/local.xml.additional.couchbase file and copy over the corresponding XML to local.xml in your Magento installation app/etc directory.
3. Clear all caches, by flushing each of the Couchbase Buckets

## Local XML Configuration
```xml
<config>
    <global>
	<models>
            <core>
                <rewrite>
                    <cache>EbayEnterprise_CouchbaseCacheDriver_Model_Core_Cache</cache>
                </rewrite>
            </core>
        </models>
 	<cache>
            <backend><![CDATA[couchbase]]></backend>
            <backend_options>
                <consoleConnectionString><![CDATA[couchbase://127.0.0.1?config_cache=/tmp/phpcb_cache_general]]></consoleConnectionString>
                <consoleUser><![CDATA[xxx]]></consoleUser>
                <consolePassword><![CDATA[yyy]]></consolePassword>
                <consoleTagBucket><![CDATA[tags]]></consoleTagBucket>
                <consoleSessionBucket><![CDATA[session]]></consoleSessionBucket>
                <consoleGeneralCacheBucket><![CDATA[magento]]></consoleGeneralCacheBucket>
                <consoleTagExpiry><![CDATA[3600]]></consoleTagExpiry>
            </backend_options>
        </cache>
        <full_page_cache>
            <backend><![CDATA[couchbase]]></backend>
            <backend_options>
                <consoleConnectionString><![CDATA[couchbase://127.0.0.1?config_cache=/tmp/phpcb_cache_fpc]]></consoleConnectionString>
                <consoleUser><![CDATA[xxxx]]></consoleUser>
                <consolePassword><![CDATA[yyyy]]></consolePassword>
                <consoleTagBucket><![CDATA[tags]]></consoleTagBucket>
                <consoleFPCBucket><![CDATA[fpc]]></consoleFPCBucket>
                <consoleSessionBucket><![CDATA[session]]></consoleSessionBucket>
                <consoleTagExpiry><![CDATA[3600]]></consoleTagExpiry>
            </backend_options>
        </full_page_cache>
    </global>
</config>
```
