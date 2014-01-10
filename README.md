mongoSolrImporter
=================

This script handles the import from mongoDB documents to solr/lucene lucene in order to index mongoDB data in solr/lucene. It was initial designed for midrange (and above) sized mongoDBs, so block processing, multi-threading (to use all CPU cores), solr cores and mongoDB replication sets are supported. The script collets the documents from mongoDB, performs some converting or field mapping, if this is required and index/store those documents to Solr.


## Requirement
You need an activated PCNTL module, if you like to use the "multi threaded" future. Otherwise use the option `-s true` to run the single thread version.

This script needs an PHP installation (tested  on 5.3.8).


## Installation
Just copy the script and config file to your system and edit the configuration for your environment.

## Run
Index your mongoDB documents to solr by running that following command on system console:
user@system>`php  mongoSolrImporter.php -c mongoSolrImporter.ini`

If you like (or need) to run in single thread use the option `-s true`
user@system>`php  mongoSolrImporter.php -c mongoSolrImporter.ini -s true`

## Current limitation
At this time of development, the mongoDB - Solr - Importer only supports flat mongo documents without embedded docs or arrays.
