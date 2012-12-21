<?php
	// Include the "database.php" file
	include 'database.php';
	
	// Set the timezone, in this case London
	date_default_timezone_set('Europe/London');
	
	// Get the current date and time
	$date = date('d-m-Y', time());
	$time = date('h:i:s A', time());
	
	// Get the SMS body and the number it was sent from
	$search = $_POST['Body'];
	$number = $_POST['From'];

	// Check whether or not the user wants to receive a link to the Wikipedia article
	if (!(strstr($search, ' (link)') === false))
	{
		$searchResult = str_replace(' ', '_', trim(ucwords(str_replace(' (link)', '', $search))));
		
		getlink($search, $searchResult, $number, $date, $time);
	}
	else
	{
		$searchResult = str_replace(' ', '_', trim(ucwords($search)));
		
		getdata($search, $searchResult, $number, $date, $time);
	}
	
	function getlink($search, $searchResult, $number, $date, $time)
	{
		global $result;

		// Make a web request for a short link to the Wikipedia article
		$curl = curl_init();
		
		curl_setopt_array($curl, array(
		    CURLOPT_RETURNTRANSFER => 1,
		    CURLOPT_URL => 'http://mee.la/api.php?url=http://en.wikipedia.org/wiki/' . $searchResult,
		    CURLOPT_USERAGENT => 'WikiText, support@wikitext.co.uk'
		));
		
		$result = curl_exec($curl);

		$http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		
		logResults($search, $searchResult, $result, $number, $date, $time);
	}
	
	function getdata($search, $searchResult, $number, $date, $time)
	{
		global $result;

		// Make a web request for the data from the Wikipedia article
		$curl = curl_init();
		
		curl_setopt_array($curl, array(
		    CURLOPT_RETURNTRANSFER => 1,
		    CURLOPT_URL => 'http://en.wikipedia.org/w/api.php?action=parse&page=' . $searchResult . '&redirects&format=json&section=0',
		    CURLOPT_USERAGENT => 'WikiText, support@wikitext.co.uk'
		));
		
		$c = curl_exec($curl);

		$json = json_decode($c);

		$content = $json->parse->text->{'*'};
		
		// Make a web request for the categories in which the article is
		$curlCategories = curl_init();
		
		curl_setopt_array($curlCategories, array(
		    CURLOPT_RETURNTRANSFER => 1,
		    CURLOPT_URL => 'http://en.wikipedia.org/w/api.php?action=parse&page=' . $searchResult . '&redirects&format=json&prop=categories',
		    CURLOPT_USERAGENT => 'WikiText, support@wikitext.co.uk'
		));
		
		$cCategories = curl_exec($curlCategories);

		$http_status = curl_getinfo($curlCategories, CURLINFO_HTTP_CODE);
		
		// Determine whether the search returns a single result or multiple results by checking if it has the category "Disambiguation pages"
		if ((strstr($cCategories, 'Disambiguation_pages')) or (strstr($cCategories, 'disambiguation_pages')))
		{
			// If the paragraph contains "may refer to:", the search must return multiple results without a definition
		    if ((strstr($c, 'may be:')) or (strstr($c, 'may refer to:')))
		    {
		    	// Web request for search
				curl_setopt_array($curl, array(
				    CURLOPT_RETURNTRANSFER => 1,
				    CURLOPT_URL => 'http://en.wikipedia.org/w/api.php?action=parse&page=' . $searchResult . '&redirects&format=json',
				    CURLOPT_USERAGENT => 'WikiText, support@wikitext.co.uk'
				));
				
				$c = curl_exec($curl);

				$json = json_decode($c);
				
				$content = $json->parse->text->{'*'};
				
				if (preg_match('/<a href=\"\/wiki\/(.*?)\"/', $content, $matches))
				{
					$resultPlainText = strip_tags($matches[1]);

				    $searchResult = $resultPlainText;
				    
				    getdata($search, $searchResult, $number, $date, $time);
				}
		    }
		    else
		    {
				if (preg_match('/<p>(.*?)<\/p>/', $content, $matches))
				{
					$resultPlainText = strip_tags($matches[1]);
					
					$result = stripbrackets($resultPlainText);

					if (strstr($content, 'infobox'))
					{
						if (preg_match('/<\/table>\n<p>(.*?)<\/p>/', $content, $matches))
						{
							$resultPlainText = strip_tags($matches[1]);
							
							$result = stripbrackets($resultPlainText);
						}
					}
					
					logResults($search, $searchResult, $result, $number, $date, $time);
				}
				else
				{
					$result = 'Wikipedia does not have an article with this exact name. Use specific words/phrases and correct capitalisation, spelling and grammar.';
					
					logResults($search . ' (fail)', $searchResult, $result, $number, $date, $time);
				}
		    }
		}
		else
		{
			if (preg_match('/<p>(.*?)<\/p>/', $content, $matches))
			{
			    $resultPlainText = strip_tags($matches[1]);
			    
			    $result = stripbrackets($resultPlainText);

			    if (strstr($content, 'infobox'))
				{
					if (preg_match('/<\/table>\n<p>(.*?)<\/p>/', $content, $matches))
					{
						$resultPlainText = strip_tags($matches[1]);
						
						$result = stripbrackets($resultPlainText);
					}
				}
			    
			    logResults($search, $searchResult, $result, $number, $date, $time);
			}
			else
			{
				$result = 'Wikipedia does not have an article with this exact name. Use specific words/phrases and correct capitalisation, spelling and grammar.';
				
				logResults($search . ' (fail)', $searchResult, $result, $number, $date, $time);
			}
		}
	}
	
	// Log data about the request in the database
	function logResults($search, $searchResult, $result, $number, $date, $time)
	{
		$q_search = mysql_real_escape_string($search);
		$q_searchResult = mysql_real_escape_string($searchResult);
		$q_result = mysql_real_escape_string($result);
		$q_number = mysql_real_escape_string($number);
		$q_date = mysql_real_escape_string($date);
		$q_time = mysql_real_escape_string($time);
		
		mysql_query("INSERT INTO WikiTextRecords_Text (Term, Result, Message, Number, Date, Time) VALUES ('$q_search', '$q_searchResult', '$q_result', '$q_number', '$q_date', '$q_time')");
	}
	
	// Remove brackets from, remove spaces from and shorten the result
	function stripbrackets($resultPlainText)
	{
		$nesting = 0;
		$result = '';
		$len = strlen($resultPlainText);
		
		for ($idx = 0; $idx != $len; $idx++)
		{
			$chr = $resultPlainText[$idx];
			if ($chr == '(' || $chr == '[')
				$nesting++;
			
			if ($nesting == 0)
				$result .= $chr;
			
			if ($chr == ')' || $chr == ']')
				$nesting--;
		}
		
		$result = str_replace(' ,', ',', $result);
		$result = str_replace(' .', '.', $result);
		$result = str_replace('  ', ' ', $result);
		$result = str_replace('   ', ' ', $result);
		$result = str_replace(' It may also refer to:', '', $result);
		$result = str_replace(', and may refer to:', '.', $result);
		$result = str_replace(', may refer in English to:', '.', $result);
		
		$result = utf8_decode($result);
		
		if (strlen($result) > 160)
			$result = substr($result, 0, 157) . '...';
		
		return $result;
	}
?>

<!-- Send an SMS back with the result -->
<Response>
    <Sms><?php echo $result; ?></Sms>
</Response>