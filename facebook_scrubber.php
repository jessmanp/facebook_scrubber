#!/usr/bin/php -f
<?

/*
* Script to check emails against Facebook via command line using cURL and PHP *
* Get IPs and setup array to rotate *
*/

// pull local IPs into array
$getIPcmd = '/sbin/ifconfig -a | grep -v "Scope:Link" | grep -v "Scope:Host" | grep "inet6 addr" | awk \'{print $3}\' | rev | cut -c 4- | rev';
$IParr = array();
exec($getIPcmd, $IParr, $ret);
sort($IParr);

/*
* Function to grab random local IPs *
*/

function rand_local_ip() {

	global $IParr;
	global $removeIP;

	if ($removeIP) {
		$IParr = array_diff($IParr, $removeIP);
	}
	
	$rand_key = array_rand($IParr, 1);
	$useIP = str_replace("\n",'',$IParr[$rand_key]);

	echo "--- NEW IP ARRAY COUNT ---\n";
	echo count($IParr)."\n\n";

	return($useIP);
}

/*
* Do remote query to update a databse field *
*/

$MAILRECS = mysql_connect ('172.16.0.15', 'user', 'password') or die ("Cannot connect to remote database");
mysql_select_db('fb_emails');

function updateData($email,$state,$fbid){
     /* DO QUERY */
	$temp_query ="insert into facebook_scrubbed_emails (person_id, status, facebook_id) select id, '$state', '$fbid' from Person where Person.email = '$email' on duplicate key update status = '$state', facebook_id = '$fbid';";
    mysql_query($temp_query);
    
	/* DO QUERY 2 */
    $temp_query2 = "update Person2MailingList left join Person on Person.id = Person2MailingList.Person_id set verified_sna = '".$state."' where Person.email = '".$email."';";
    mysql_query($temp_query2);
}

function updateP2ML($email,$state){
	/* DO QUERY 3 */
	$temp_query3 = "update Person2MailingList left join Person on Person.id = Person2MailingList.Person_id set verified_sna = '".$state."' where Person.email = '".$email."';";
	mysql_query($temp_query3);
}

/* XXXXXXXXXXXXXXXXXXXXXXXXXXX START SCRIPT XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX */

if (isset($argv[1]) && isset($argv[2]) && isset($argv[3]) && $argv[1] != '' && $argv[2] != '' && $argv[3] != '') {


$theDate = date("m/d/Y : H:i:s", time());
echo "--- RUN @ " . $theDate." ---\n";
echo "--- BEGIN FACEBOOK EMAIL SEARCH QUERY ---\n\n";

$rotateNum = $argv[1];
$src_file = $argv[2];
$out_file = $argv[3];

$source_interface = rand_local_ip();

/* read in user list and grab email parts */
          $handle = @fopen($src_file, "r");
          if ($handle) {
	     $county = 0;
	          while (!feof($handle)) {
                          $line = fgets($handle, 1024);
                          if($line != '') {
                                  $line_to_delete = $line;
                                  //echo $line."\n"; echo "=============================================\n";
                                  $order = array("\r\n", "\n", "\r");
                                  $line = str_replace($order,'',$line);
				  $line = explode("|",$line);
				  $line = $line[0];
                                  //echo $line."\n"; echo "=============================================\n"; exit();


				if ($source_interface) {

							$email = $line;

							// validate EMAIL to make sure its valid
							if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
							//echo $email." is VALID\n"; exit();
							////////////////////////////////////////////////////////////////////////////////////////////////////////
							//								*** VALID EMAIL START ***											////
							////////////////////////////////////////////////////////////////////////////////////////////////////////

								// only allow valid email extensions (.com .net .edu)
								$email_parts = explode("@",$email);
								$domain = $email_parts[1];
								$validDomain1 = stripos($email, ".com");
								$validDomain2 = stripos($email, ".net");
								$validDomain3 = stripos($email, ".edu");
								if ($validDomain1 !== false || $validDomain2 !== false || $validDomain3 !== false) {
								// *********************************** START VALID DOMAIN *********************************** //


									if ($county == $rotateNum) {
										$source_interface = rand_local_ip();
										$county = 0;
										echo "--- IP Rotation:".$rotateNum." Using IP:".$source_interface." ---\n";
									} else {
										echo "--- Using IP:".$source_interface." ---\n";
									} // END if county

									/*
									* DO SEARCH VIA URL ON FACEBOOK *
									*/

									 $ch = curl_init();
									 curl_setopt($ch, CURLOPT_INTERFACE, $source_interface);
									 curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
									 curl_setopt($ch, CURLOPT_TIMEOUT, 300);
									 curl_setopt($ch, CURLOPT_URL, 'https://www.facebook.com/search.php?q='.urlencode($email));
									// curl_setopt($ch, CURLOPT_POSTFIELDS, $thePostFields);
									// curl_setopt($ch, CURLOPT_POST, 1);
									 curl_setopt($ch, CURLOPT_HEADER, 0);
									 curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
									 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
									 curl_setopt($ch, CURLOPT_COOKIEJAR, str_replace('\\','/',dirname(__FILE__)).'/fb_cookies.txt');
									 curl_setopt($ch, CURLOPT_COOKIEFILE, str_replace('\\','/',dirname(__FILE__)).'/fb_cookies.txt');
									 curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
									 curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (X11; Linux i686; rv:21.0) Gecko/20100101 Firefox/21.0");
									 $html = curl_exec($ch);

									 $err = 0;
									 $err = curl_errno($ch);
									// print_r(curl_getinfo($ch));
									 curl_close($ch);

									 if ($err != 0){
											echo '*** cURL ERROR='.$err." for ".$email." ***\n";
											echo "\n////////////////////////////////////////////////////////////////////////\n";
											echo "--- FACEBOOK CONNECTION ERROR REMOVING IP (".$source_interface.") ---\n";
											echo "////////////////////////////////////////////////////////////////////////\n\n";
											/* WRITE TO A NEW FILE */
											$stringDatae = "";
											$fbFilee = $out_file."_retry";
											$fe = fopen($fbFilee, 'a') or die("can't open file");
											$stringDatae .= $email."\n";
											fwrite($fe, $stringDatae);
											fclose($fe);
											$removeIP = array();
											$removeIP[] = $source_interface;
											$source_interface = rand_local_ip();
									 } else {
											 //echo $html; exit();
											 //sleep(1);
										  if (stripos($html, '<div id="captcha" class="captcha"')) {
													 echo "\n@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@\n";
												 echo "--- FACEBOOK ERROR PAGE REMOVING BLOCKED IP (".$source_interface.") ---\n";
												 echo "@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@@\n\n";
												 /* WRITE TO A NEW FILE */
													 $stringDatae = "";
													 $fbFilee = $out_file."_retry";
													 $fe = fopen($fbFilee, 'a') or die("can't open file");
													 $stringDatae .= $email."\n";
													 fwrite($fe, $stringDatae);
													 fclose($fe);
												 	$removeIP = array();
													 $removeIP[] = $source_interface;
												 	$source_interface = rand_local_ip();
												 	//exit();
										  } else {

												$found_result = stripos($html, "pagelet_search_no_results");
												$found_result2 = stripos($html, "sign_up_dialog");
														if ($found_result !== false || $found_result2 !== false) {
																echo "*** FACEBOOK SEARCH RESULT ERROR for ".$email." ***\n";
																/* WRITE TO A NEW FILE */
																$stringDatar = "";
																$fbFiler = $out_file."_error";
																$fr = fopen($fbFiler, 'a') or die("can't open file");
																$stringDatar .= $email."\n";
																fwrite($fr, $stringDatar);
																fclose($fr);
																updateP2ML($email,0);
														} else {

													//echo "\n\n~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~".$html."~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~\n\n"; exit();

													// find first part
															$prepos1 = stripos($html, ",&quot;id&quot;:");
															$pos1 = ($prepos1+16);
															// find second part
															$pos2 = stripos($html, ",", $pos1);
															// return between
															$restr = substr($html, $pos1, ($pos2-$pos1));

															//echo $pos1."\n";
															//echo $pos2."\n";

															$fbID = $restr;
															$restr = null;

															//echo "EMAIL=".$email." FBID=".$fbID."\n\n"; exit();

															if (isset($fbID) && is_numeric($fbID)) {
															/* WRITE TO A NEW FILE */
																		$stringData = "";
																		$fbFile = $out_file;
																		$fh = fopen($fbFile, 'a') or die("can't open file");
																		$stringData .= $email."\n";
																		fwrite($fh, $stringData);
																		fclose($fh);
																		//del_line_in_file($src_file, $line_to_delete);
																		updateData($email,1,$fbID);
																		echo $email." **ADDED**\n";
															} // END fbid
														} // END found result
											} // END strpos
									 } // END if err not 0

								// *********************************** END VALID DOMAIN *********************************** //
								}
							////////////////////////////////////////////////////////////////////////////////////////////////////////
							//								*** VALID EMAIL END ***											////
							////////////////////////////////////////////////////////////////////////////////////////////////////////
							}

				} else {
					mysql_close($MAILRECS);
					echo "\n!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
				    echo "--- OUT OF IPs... STOPPING SCRIPT ---\n";
				    echo "--- *** LAST EMAIL PROCESSED [".$line."] ***\n";
					$theStopDate = date("m/d/Y : H:i:s", time());
					echo "--- STOPPED @ " . $theStopDate." ---\n";
				        echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n\n";
					exit();
				} // END if source_interface

echo "==========================================================================\n";
                                  
                                  


                          }
		      $county++;
                  } // END $handle
                  fclose($handle);
          }


}
mysql_close($MAILRECS);
echo "\nXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX\n";
echo "--- OUT OF EMAILS... STOPPING SCRIPT ---\n";
$theStopDate = date("m/d/Y : H:i:s", time());
echo "--- STOPPED @ " . $theStopDate." ---\n";
echo "XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX\n\n";
?>