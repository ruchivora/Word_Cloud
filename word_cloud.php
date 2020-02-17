<?php
	if($_REQUEST['Debug']==1){
	error_reporting(E_ALL); ini_set('display_errors', 1); 
	}

  ini_set('max_execution_time', -1);
  date_default_timezone_set('Asia/Kolkata');
	
  include_once (dirname(__FILE__))."/../config.php";

  $Elastic      =	$GLOBALS['Elastic'];
    

  $parameter['index']    = "instagram_feed_master_new";
  $parameter['type']     = "feed";
  $result                = $Elastic->search($parameter);

   // $start_date   = "22/01/2019";
   // $end_date     = "22/02/2019";
  $business_id  = "17841400997949751";
  $handle_name  = "theshilpashetty";

   /*
		get post_id in the given date range or if date_range is not 
		given then find post_id of latest 20 posts.
   */

    if($start_date == "" && $end_date == "" )
    {
    	$get_post_id  = '{
		  				     "_source": ["id","create_date","user.business_id"], 
		  				     "query": {
		   					            "match": {"user.business_id": "'.$business_id.'"}
		                              },
		 					  "sort": [
								        {
								          "create_date": {"order": "desc"}
								        }
								     ],
							  "size": 20
			            }';	
    }
    else
    {
    	$get_post_id = '{
						  "_source": ["id","create_date","user.business_id"]
						  , "query": {
						    "bool": {
						      "must": [
						        {"match": {"user.business_id": "17841400997949751"}},
						        {
						          "range": {
						            "create_date": {
						              "format": "dd/MM/yyyy", 
						              "gte": "'.$start_date.'",
						              "lte": "'.$end_date.'"
						            }
						          }
						        }
						      ]
						    }
						  }
						  
						}
						';
    }

	$final_query           = json_decode($get_post_id,true);
	$parameter['body']     = $final_query ;
	$results               = $Elastic->search($parameter);

foreach ($results['hits'] as $key => $value) 
{
	foreach ($value as $key_1 => $value_1) 
	{
		$post_id[]= $value_1['_source']['id'];
	}
}
/*
	This post_id is teporary added because latest post
	 does not have sentiment.
*/
$post_id[] = "18016892101077061";

$post_id = implode('","',$post_id);
 
    $par['index'] = "instagram_feed_comments";
    $par['type']  = "comment";
    $par['size']   = 100;
    $par['scroll']  = "30m";

    $get_data_from_post  = '{
								  "_source": [
								    "text",
								    "sentiment",
								    "post_id"
								  ],
								  "query": {
								    "bool": {
								      "must": [
								        {
								          "terms": {"post_id": ["'.$post_id.'"]}
								        }
								      ]
								    }
								  },
								  "size": 1000
							 }'; 

    $final_query     = json_decode($get_data_from_post,true);
	$par['body']     = $final_query ;
	$results         = $Elastic->search($par);

$stopwords = array("i", "me", "my", " myself", "we", "our", "ours", "ourselves", "you", "your", "yours", "yourself", "yourselves", "he", "him", "his", "himself", "she", "her", "hers", "herself", "it", "its", "itself", "they", "them", "their", "theirs", "themselves", "what", "which", "who", "whom", "this", "that", "these", "those", "am", "is", "are", "was", "were", "be", "been", "being", "have", "has", "had", "having", "do", "does", "did", "doing", "a", "an", "the", "and", "but", "if", "or", "because", "as", "until", "while", "of", "at", "by", "for", "with", "about", "against", "between", "into", "through", "during", "before", "after", "above", "below", "to", "from", "up", "down", "in", "out", "on", "off", "over", "under", "again", "further", "then", "once", "here", "there", "when", "where", "why", "how", "all", "any", "both", "each", "few", "more", "most", "other", "some", "such", "no", "nor", "not", "only", "own", "same", "so", "than", "too", "very", "s", "t", "can", "will", "just", "don", "should", "now",'know','repost','photo','good','better','best','credit','photograph','link','bio','click','hear','instagram','facebook','youtube','follow','like','comment','january','february','march','april','may','june','july','august','september','october','november','december','monday','tuesday','wednesday','thursday','friday','saturday','sunday','full','head','please','sorry','check','much','call', $handle_name,'can\'t','it\'s');
 
  $account_name = "Shilpa Shetty Kundra";
  $one_word     = explode(" ",$account_name);

  $account_stopword = customize($one_word);

  foreach ($account_stopword as $key => $value)
  {
  	$stopwords[] = $value;
  } 
/*
	Creates pagination
*/
while (isset($results['hits']) && count($results['hits']['hits']) > 0) 
{
	foreach ($results['hits']['hits'] as $key => $value_1)
	{
		$text_and_sentiment[] = $value_1['_source'];
		$text_array[] = $value_1['_source']['text'];			
	}
	
	$scroll_id = '';
	$scroll_id = $results['_scroll_id'];
	$results = $Elastic->scroll([
        "scroll_id" => $scroll_id,  
        "scroll" => "30m"           
    ]);
}
/*
	$text_and_sentiment array is split and new array 
	$master_array contains individual word with it's sentiment.
*/
$count=0;
foreach($text_and_sentiment as $key => $value)
{
	$words     = array_count_values(str_word_count($value['text'], 1));	
	$sentiment = ($value['sentiment'] == "") ? "NA" :$value['sentiment']; 
	$master_array[$count][$sentiment] = $words;	
	$count++;
}

/*
	word_sentiment = sentient of each word.
*/
$word_sentiment = array();

foreach ($master_array as $key => $value)
 {
	foreach ($value as $key_1 => $value_1)
	 {
		foreach ($value_1 as $key_2 => $value_2) 
		{
			if(!in_array(strtolower($key_2),$stopwords) && strlen($key_2) >=4)
			{
				$word_sentiment[strtolower($key_2)][$key_1] = $word_sentiment[strtolower($key_2)][$key_1]+1;
			}				
		}	
	}	
}

/*
	count key is added to the word_sentiment to sort the word_sentiment.
*/
foreach($word_sentiment as $key => $value)
{
	$count = 0;
	foreach ($value as $key_1 => $value_1) 
	{
		$count = $count+$value_1;		
	}
	$word_sentiment[$key]['count'] = $count;
}

/*
	The word_sentiment is sorted and only top 60 words are considered.
*/
$counter = array_column($word_sentiment,'count');
array_multisort($counter,SORT_DESC,$word_sentiment);
$word_sentiment = array_slice($word_sentiment,0,60,true);

/*
	The below code will be executed only if there are 
	sentiments wih sae count eg: positive = 20, negative =20. 
*/
foreach ($word_sentiment as $key => $value)
{
	unset($word_sentiment[$key]['count']);
	$sentiment_variant = count($word_sentiment[$key]);

		if($sentiment_variant > 1)
		{
			if( $sentiment_variant == count(array_unique($word_sentiment[$key])) )
			{
				$count = 0;
				foreach ($value as $key_1 => $value_1)
				 {
					$count++;
					if($count >1) unset($word_sentiment[$key][$key_1]);				
				  }
			}
			else
			{
				$max ;
				$sentiment = array();
				$index_counter = 0;
				foreach ($value as $key_1 => $value_1) 
				{
					$index_counter ++;
					if($index_counter == 1 )
					{
						$max = $word_sentiment[$key][$key_1];	
						$sentiment[$key_1] = priority($key_1); 
					}
					else
					{
						if($word_sentiment[$key][$key_1] == $max) $sentiment[$key_1] = priority($key_1);	
					}
					unset($word_sentiment[$key][$key_1]);
				}
				$final_sentiment = array_keys($sentiment,max($sentiment));
				$sentiment_value = $final_sentiment[0];
				$word_sentiment[$key][$sentiment_value] = $max;
			}	
		}	
}
/*
	Splits each comment into individual word.
	(done again because data is needed in specific format)
*/
foreach($text_array as $key => $value)
{
	$words  = array_count_values(str_word_count($value, 1));	
	$temp[] = $words;
}
/*
	case insesitive : eg:beautiful and Beautiful is considered as one word.
*/
$count_of_word_case_insensitive = array();
foreach ($temp as $key => $value)
{	
	foreach($value as $key_1 => $value_1)
	{
		$key_1 = strtolower($key_1);
		if(!in_array($key_1,$stopwords) && strlen($key_1) >=4)
		{
			if(array_key_exists($key_1,$count_of_word_case_insensitive))
			{
				$count = $count_of_word_case_insensitive[$key_1]+1;
				$count_of_word_case_insensitive[$key_1] = $count;
			}
			else
			{
				$count_of_word_case_insensitive[$key_1] = $value_1;
			}
		}
	}
}
/*
	$count_of_word_case_insensitive is sorted and only first 50 words are considered.
*/

arsort($count_of_word_case_insensitive);
$count_of_word_case_insensitive = array_slice($count_of_word_case_insensitive,0,50,true);

/*
	case sensitive eg. beautiful and Beautiful 
	is considered as two different word.
*/
$count_of_word = array();
foreach ($temp as $key => $value)
{	
	foreach($value as $key_1 => $value_1)
	{
		if(!in_array(strtolower($key_1),$stopwords) && strlen($key_1) >=4)
		{
			if(array_key_exists($key_1,$count_of_word))
			{
				$count = $count_of_word[$key_1]+1;
				$count_of_word[$key_1] = $count;
			}
			else
			{
				$count_of_word[$key_1] = $value_1;
			}
		}
	}
}
arsort($count_of_word);
$specific_word_count = array();
/*
	gives specific word count eg. beautiful =10,Beautiful = 20
*/
foreach( $count_of_word as $key => $value)
{
	$specific_word_count[strtolower($key)][$key] = $value;
}
/*
	To sort the array a count key is inserted. 
*/
foreach ($specific_word_count as $key => $value) 
{
	$sum = 0;
	foreach ($value as $key_1 => $value_1) 
	{
		$sum = $sum + $value_1;
	}
	$specific_word_count[$key]['count'] = $sum;
}

$counter = array_column($specific_word_count,'count');
array_multisort($counter,SORT_DESC,$specific_word_count);

$specific_word_count = array_splice($specific_word_count,0,60,true);

$array_index = 0;
foreach($count_of_word_case_insensitive as $key => $value)
{
	$final_array[$array_index]['word']       = key($specific_word_count[$key]);
	$final_array[$array_index]['weight']     = $value;
	$final_array[$array_index]['sentiment']  = key($word_sentiment[$key]);

	$array_index++;			
}
print_r($final_array);

	exit();

print_r($word_sentiment);
print_r($count_of_word_case_insensitive);
print_r($specific_word_count);

/*
	sentiment is assigned priority in case if two
	sentiment has same count.
*/
function priority($key)
{
	switch($key)
	{
		case "NA" : return 1;
		break;
		case "neutral" : return 2;
		break;
		case "positive" : return 3;
		break;
		case "negative" : return 4;
		break;
	}	
}
function customize($one_word)
{
	$loop_size = sizeof($one_word);

	for ($outer_loop= 0 ; $outer_loop < $loop_size ; $outer_loop++)
	{
		for($inner_loop = $outer_loop ; $inner_loop < $loop_size ; $inner_loop++)
		{
			if($one_word[$outer_loop] !=$one_word[$inner_loop])
			{
				$account_stopword[] = strtolower($one_word[$outer_loop].$one_word[$inner_loop]);
				$account_stopword[] = strtolower($one_word[$outer_loop]." ".$one_word[$inner_loop]);
			}
			else
			{
				$account_stopword[] = strtolower($one_word[$outer_loop]);
			}
		}
	}
	return $account_stopword;
}

?>