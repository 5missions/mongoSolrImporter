mongoSolrImporter
=================

This script handles the import from mongoDB documents to solr/lucene lucene in order to index mongoDB data in solr/lucene. It was initial designed for midrange (and above) sized mongoDBs, so block processing, multi-threading (to use all CPU cores), solr cores and mongoDB replication sets are supportet. The script collets the documents from mongoDB, performs some converting or field mapping, if this is required and index/store those documents to solr.
