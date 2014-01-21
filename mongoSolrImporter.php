<?php
/**
* mongoSolrImporter file
*
* @author 5missions.de <5missions@gmx.de>
* @license Apache License 2
* @version 1.0
* @link https://github.com/5missions/mongoSolrImporter
*/




/** 
 * handles all operation on mongoDB side, like connecting, fetching results, etc
 * 
 * @author thr  - 19.12.2013 basics, class mongoSide, function main()
 */
class mongoSide
{

    /**
     * setup mongo connection and return the collection
     * @param type $ms Mongo Seed list (singel server or list of servers)
     * @param type $mdb Mongo DB Name
     * @param type $rs replicaset, if used
     * @param type $collection the collection name which should be retruned
     * @return type Mongo collection object
     * 
     * @author thr  - 07.01.2014
     */
    public function connectMongoCollection($ms,$mdb,$rs = NULL,$collection)
    {
       $db = $this->getMongoDbHandle($ms,$mdb,$rs);
       // if you use mongo driver >= 1.3 use
       // return new MongoCollection($db, $collection);
       return $db->selectCollection($collection);
    }
    
    
    /**
     * fetches block of mongo docs and return those docs
     * @param type $collection the collection object
     * @param type $query query statement to select specific docs
     * @param type $skip  offset for running in blocks
     * @param type $limit the size of a bundle of documents, that should be returned
     * 
     * @author thr  - 07.01.2014
     */
    public function fetch($collection,$query,$skip,$limit)
    {
        $mongoCursor=$collection->find($query)->limit($limit)->skip($skip);
        return $mongoCursor;
    }


    /**
     * set up mongoDB connection
     * 
     * @param type $seedList
     * @param type $db
     * @param type $replSet
     * @param type $username
     * @param type $password
     * @return type
     * @throws Exception
     * 
     * @author thr  - 07.01.2014
     */
    private function getMongoDbHandle($seedList, $db = NULL, $replSet = NULL, 
                                      $username = NULL, $password = NULL) 
    {

        if(is_null($db))
            throw new Exception("$db should not be null\n");   

        $options = array(
                'replicaSet' => $replSet,
                'username' => $username,
                'password' => $password,
                'fsync' => true,
                );
        // remove empty array elements if no user/password is set
        $options = array_filter($options);

        $mongo = new Mongo($seedList, $options);

        // check if mongo is ready to use
        $checkConnection = $mongo->selectDB($db)->listCollections(); 

        return $database = $mongo->$db;
    }
    
    
 }
    
/**
 * class  to map/migrate mongo docs to solr docs
 * including transforming special mongo objects (like mongoID, mongoDate)
 * to solr syntax
 * 
 * @author thr  - 07.01.2014
 */
 
class solrMongoBridge
{
    /**
     * convert each mongoDoc to an solrDoc
     * including mapping of field names
     * 
     * @param type $mongoCursor the cursor to the mongo result
     * @param type $fields the field-mapping information from config
     * @return array of solrdocs
     * 
     * @author thr  - 07.01.2014
     */
    public static function convertDocs($mongoCursor, $fields, $mvchr)
    {
        $solrDocs = array();
        foreach ($mongoCursor as $mongoDoc) 
        {
            $doc = new SolrInputDocument();
            //process every field from current doc
            foreach ($fields as $mongoField=>$solrField)
            {
             //do some convertion on special fieldtypes, like MongoID oer MongoDate
             $solrPreparedField = self::prepareField($mongoDoc[$mongoField], $mvchr); 
             $doc->addField($solrField,$solrPreparedField);
            }
            //add current doc to an array of solr docs
            array_push($solrDocs,$doc);
        }
            
        return $solrDocs;
    }
    
    /**
     * handles mongo-specific field types (like MongoID or MongoDate)
     * to convert those objects to solr-like syntax
     * and converts arrays to multiValuedFields with seperator
     * 
     * @param type $mvfchr
     * @param type $field
     * @return type
     * 
     * @author thr  - 07.01.2014
     */
    private static function prepareField($field, $mvchr)
    {
        if (is_object($field))
        {
            $fieldType=get_class($field);
            switch ($fieldType)
            {
                case "MongoId":
                    //return MongoID as string
                    $field=(string) $field;
                    break;
                case "MongoDate":
                    //return MongoDate als SOLR compatible DATE/TIME string
                    //including micro seconds
                    $field=date('Y-m-d\TH:i:s', $field->sec).
                                '.'.$field->usec.'Z';
                    break;
            }
        }
        
        elseif (is_array($field))
        {
            foreach ($field as $element)
            {//$mvchr beachten!!
                $returnArray [] = self::prepareField($element, $mvchr);
            }
            $field = implode ($mvchr, $returnArray);
        }
        
        return (string)$field;
    }
    
    
}

/**
 * class that handles all operations on solr side, like
 * set up connection or insert docs
 * 
 * @author thr  - 07.01.2014
 */
class solrSide
{
    /**
     * establishs a connection to a specific solr host/core
     * 
     * @param type $host
     * @param type $port
     * @param type $path
     * @return \SolrClient
     * 
     * @author thr  - 07.01.2014
     */
    function connectSolrCore($host,$port,$path)
    {
        $options = array
                (
                    'hostname' => $host,
                    'port'     => $port,
                    'path'     => $path,
                );

        return new SolrClient($options);
    }
}

/**
 * this is the enhanced function of parse_ini_file, because the php implementation
 * does't support numeric values or empty arrays (important, if your mongo
 * query should fetch the whole db )
 * 
 * @param type $file
 * @return array $init the array of init parameter
 * @author thr  - 08.01.2014
 */
function parse_config_file($file)
{
    //loading init file
    $init = array_filter(parse_ini_file($file,1));
    //postprocessing: interprete numeric values as numbers, remove ampty arrays
    array_walk_recursive($init, function(&$value, $key)
    { 
        if(is_numeric($value))
        {
         $value = (string)((int)$value)===$value
                ?(int)$value
                :(double)$value;
        }
    });
    $init["mongo"]["QUERY"] = array_filter($init["mongo"]["QUERY"]);
    
    return $init;
}



/**
 * run the programm in single thread mode
 * 
 * @author thr  - 19.12.2013
 */
function run_singelthread($init)
{

    $cmdOption = getopt("c:");
    
    $init = parse_config_file($cmdOption["c"]);
    $skip = 0;
    //init mongo and solr handler
    $mS = new mongoSide();
    $sS = new solrSide();
    
    $mongoCollection = $mS->connectMongoCollection($init["mongo"]["HOSTS"],
                                                   $init["mongo"]["DB"],
                                                   $init["mongo"]["REPL"],
                                                   $init["mongo"]["COL"]);
    
    $mongoDocNumbers = $mongoCollection->count($init["mongo"]["QUERY"]); 
    
    //processing blocks of mongo docs
    
    while($skip<$mongoDocNumbers)
    {
        //load block of docs from solr
        $mongoCursor = $mS->fetch($mongoCollection,$init["mongo"]["QUERY"],$skip,$init["mongo"]["STEPSIZE"]);
        //set counter for next block
        $skip = $skip+$init["mongo"]["STEPSIZE"];
        //convert loaded mongoDB docs to solr
        $solrDocs = solrMongoBridge::convertDocs($mongoCursor,$init["fields"], $init["solr"]["MVCHR"]);
        $solrClient = $sS->connectSolrCore($init["solr"]["HOST"],$init["solr"]["PORT"],$init["solr"]["PATH"]);
        //push blocks of documents to solr
        $solrClient->addDocuments($solrDocs,0,1);
        echo "Imported docs: $skip \n";
    }
    
}

/**
 * Skeleton of a fork
 * 
 * @param type $skip
 * @param type $init
 * @author thr  - 10.01.2014
 */
function run_parallel($skip, $init)
{

    //init mongo and solr handler
    $mS = new mongoSide();
    $sS = new solrSide();
    
    $mongoCollection = $mS->connectMongoCollection($init["mongo"]["HOSTS"],
                                                   $init["mongo"]["DB"],
                                                   $init["mongo"]["REPL"],
                                                   $init["mongo"]["COL"]);
   
    //load block of docs from solr
    $mongoCursor = $mS->fetch($mongoCollection,$init["mongo"]["QUERY"],$skip,$init["mongo"]["STEPSIZE"]);
    //convert loaded mongoDB docs to solr
    $solrDocs = solrMongoBridge::convertDocs($mongoCursor,$init["fields"], $init["solr"]["MVCHR"]);
    $solrClient = $sS->connectSolrCore($init["solr"]["HOST"],$init["solr"]["PORT"],$init["solr"]["PATH"]);
    //push blocks of documents to solr
    $solrClient->addDocuments($solrDocs,0,1);
    echo "Imported docs: $skip - ".($skip + $init["mongo"]["STEPSIZE"])." \n";

    
}

function start_forks($init)
{
    $skip = 0;
    $execute = 0;
    $mS = new mongoSide();
    
    
    $mongoDocNumbers = $mS->connectMongoCollection($init["mongo"]["HOSTS"],
                                                   $init["mongo"]["DB"],
                                                   $init["mongo"]["REPL"],
                                                   $init["mongo"]["COL"])
                          ->count($init["mongo"]["QUERY"]);
    
    echo "$mongoDocNumbers found - start Solr import.\n";
    while($skip<$mongoDocNumbers)
    {
        $pid = pcntl_fork();
        if ($pid == -1) {die("could not fork");}
        elseif ($pid) 
        {
            $execute++;
            if ($execute>=$init["PARALLEL"]){
                pcntl_wait($status);
                $execute--;
                }
        }
        else{
            //print "\nstart child $execute at doc $skip\n"; 
            run_parallel($skip,$init);
            exit($execute);
        }

     $skip = $skip+$init["mongo"]["STEPSIZE"];
       
    }
}


function main()
{ 
$cmdOption = getopt("c:s:");
$init = parse_config_file($cmdOption["c"]);

//single thread mode active?
if(isset($cmdOption["s"]))
{
    if ($cmdOption["s"]=="true")
        run_singelthread($init);
}
else
   start_forks($init);
}


//ready, go
main();



?>
