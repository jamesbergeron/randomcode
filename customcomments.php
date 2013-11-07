<?php
/*
Plugin Name: Custom SMF Comments
Plugin URI: http://www.drivesideways.com/smfplugins/custom_comments_2_0.php.gz
Description: Adds comments to SMF from wordpress
Version: 2.0
Author: James Bergeron
Author URI: http://www.drivesideways.com/  
*/

// In case we're running standalone, for some odd reason

add_filter('comment_form', 'SMF_Comments' );

/***********************************************************************
* SMF_Comments
*
* This is the main function called from single.php in the wordpress
* theme directory.  This function handles everything by handing off 
* to many other functions.
*
* Inputs -> WP_ID which is the postID of the wordpress post
* Ouputs -> None just stuff to the screen
* 
* HOW TO:
*  In single.php in wordpress you need to add the following lines at the top of the file:
*
*   if(file_exists("/path/to/forum/SSI.php")) {
*       require_once("/path/to/forum/SSI.php");
*        global $boardID;
*        $boardID = '19571'; // where boardID is the board number you want to post to by default.
*    }
*
*   Also add the following where you want the num comments to show up:
*     do_action('comment_form', $post->ID);
*  
***********************************************************************/
function SMF_Comments( $WP_ID )
{
  $host = $_SERVER['SERVER_NAME'];
  global $wp_query, $context, $settings, $db_prefix, $modSettings;
  $PostDate = strtotime($wp_query->post->post_date);
  $CurrentTime = time();
  if ($wp_query->post->post_status != "publish" || ($PostDate > $CurrentTime)) 
    return;
  
  $ForumID = get_post_meta($wp_query->post->ID,forumid);
   // first thing does Forum_ID exist
    if ($ForumID[0] == "") { 
     if ($wp_query->post->comment_status != "closed") 
       $ForumID[0] =  Forum_AddWPPost($WP_ID);
    } 
    if ($ForumID[0] != "") { 
     // It exists so this is easy let's Display the number of comments and a box to post in
     $NumPosts = Forum_GetNumPostsInThread($ForumID[0]);
      echo '<div><h3 id="comments"><a href="http://' . $host . '/forum/index.php/topic,'. $ForumID[0] . '.0.html?">';
      switch ($NumPosts) {
        case 0:
              echo "Be the first to comment >>";
              break;
        default: 
              echo "Readers comments (" . $NumPosts .") >>";
      }
    echo "</a></h3></div>";
   }
  return;
}
/**********************************************************************
* Forum_GetNumPostsInThread
*
* Returns number of posts in thread by default no display
* If Display is true then it prints it out
**********************************************************************/
function Forum_GetNumPostsInThread($threadID = null, $Display = false)
{
  global $db_prefix, $settings, $modSettings, $smcFunc;
   //  Get ThreadID for PostID.
        $request = $smcFunc['db_query']('',"SELECT count(*) FROM {$db_prefix}messages WHERE id_topic = $threadID",array());

        $aCount = mysql_fetch_row($request);
        $NumPosts = $aCount[0] - 1;
      if (!$Display)
        return $NumPosts;

     // Display is set to true so print out nicely.
     echo $NumPosts;
}



/***********************************************************************
* Forum_AddWPPost
*
* This function gets the excerpt from wordpress and adds it as a new
* post into the forum and then returns the forum Post id
*
***********************************************************************/
function Forum_AddWPPost($WP_ID)  {
   global $wp_query , $boardID;
   $posterInfo = "Autos_Editor";
   $PostTitle = $wp_query->post->post_title;
   $PostContent = $wp_query->post->post_excerpt;
   $PostLink =  get_permalink($WP_ID);
   $PostTitle = mysql_real_escape_string($PostTitle);
   $PostTitle =  preg_replace("/[\xE2]/", "\'", $PostTitle);
   $PostTitle =  preg_replace("/[^\x9\xA\xD\x20-\x7F]/", "", $PostTitle);
   $PostData = mysql_real_escape_string($PostContent);
   $PostData =  preg_replace("/[\xE2]/", "\'", $PostData);
   $PostData =  preg_replace("/[^\x9\xA\xD\x20-\x7F]/", "", $PostData);
   $PostData =  strip_tags($PostData,'<img>');
   $PostData =  $PostData . '<br>[url=' . $PostLink . ']Read More...[/url]';

   // Before we do anything else let's check which category this post is in.  If it is expected to be different from the default change board.
   // NOTE: Change this if you would like to post to different boards based on what category the blog posting is in.
     if (in_category( array("general-news","auto-shows") )) {
        $boardID = '19591'; // post in my news board instead
    }

   // Just before adding the topic again, let's double check to make sure that it doesn't exist... just in case
   $CheckForumID = get_post_meta($wp_query->post->ID,forumid);
    if ($CheckForumID[0] == "") {
      $ForumID = createTopic($PostTitle, $PostData, $posterInfo, $boardID);
    } else {
      // already existed just return it
      return $CheckForumID[0];
    }
   // This line adds the meta key into the wordpress blog
   add_post_meta($WP_ID, forumid, $ForumID, true);
   return $ForumID;
}


function Forum_PostBox($Forum_ID,$NumPosts) {
   global $_SESSION, $wp_query, $context, $user_info, $settings;
 //if ($context['user']['is_logged']) {;
     if ($Forum_ID != null) {
       global $user_info;

   // The stack hasn't been created yet.  Start it off with a single bit in it and move the pointer to 0.
     if (!isset($_SESSION['form_stack']) || !isset($_SESSION['form_stack_pointer']))
     {
         $_SESSION['form_stack'] = chr(1);
         $_SESSION['form_stack_pointer'] = 0;

      }
      $context['form_sequence_number'] = $_SESSION['form_stack_pointer']++;
     ?>
     <p>
      <input type="text" style="color:#000; background:#ccc" name="author" readonly="readonly" id="author" value="<?php echo $context['user']['name']; ?>" size="22" tabindex="1" />
<label for="author"><small>Name <?php if ($req) echo "(If this is not you "; ssi_logout($hyperlink); echo ")";?></small></label></p>
  <form action="http://forum/index.php?action=post2" method="post" name="postmodify" onsubmit="submitonce(this);" style="margin: 0;">
       <input type="hidden" name="topic" value="<?php echo $Forum_ID;?>" />
       <input type="hidden" name="subject" value="<?php echo $wp_query->post->post_title;?>" />
       <input type="hidden" name="icon" value="xx" />
       <input type="hidden" name="notify" value="0" />
       <input type="hidden" name="goback" value="2" />
       <input type="hidden" name="num_replies" value="<?php $NumPosts = $NumPosts - 1; echo $NumPosts;?>" />
       <small> This box supports BBC code </small>
          <textarea cols="75" rows="7" style="width: 95%; height: 100px;" name="message" tabindex="1"></textarea><br />
          <p><input type="submit" name="post" value="Submit Comment" onclick="return submitThisOnce(this);" accesskey="s" tabindex="2"/>
          <input type="hidden" name="sc" value="<?php echo $context['session_id'];?>" />
          <input type="hidden" name="seqnum" value="<?php echo $context['form_sequence_number'];?>" />
  </form>
<?//}
 }
}

function createTopic($subject, $body, $posterInfo, $boardID, $icon = 'xx', $timestamp = 0)
   {
   global $db_prefix, $modSettings, $smcFunc;

   // Do some setup work
   if ( $timestamp <= 0 || !is_numeric($timestamp) )
      $timestamp = time();
   if ( empty($subject) || empty($body) || empty($boardID) || empty($posterInfo) ) 
      return false;
   if ( !is_array($posterInfo) )
   {
      if ( is_numeric($posterInfo) ) // ok its the ID
         $where = " ID_MEMBER = $posterInfo";
      else // ok its the name
        $where = " real_name = '$posterInfo'";

      $res = $smcFunc['db_query']('',"
          SELECT ID_MEMBER, real_name, email_address
		     FROM {$db_prefix}members
	  WHERE $where LIMIT 1",array());
      if (mysql_num_rows($res) == 0 )
         return false;

	  $row = mysql_fetch_row($res);
      $posterInfo = array ( 'id' => $row[0], 'name' => $row[1], 'email' => $row[2]);

	  mysql_free_result($res);

	} // end if is poster

// Check to make sure topic doesn't exist, if it does quit
   $test = "subject = '$subject'";
   $myanswer = $smcFunc['db_query']('',"
          SELECT ID_TOPIC,poster_time
           FROM {$db_prefix}messages
          WHERE $test", array()) ;
    
   if (mysql_num_rows($myanswer) != 0) {
       $NumberofPostsFound = mysql_num_rows($myanswer);
       for ($i=0; $i <= $NumberofPostsFound; $i++) {
         $PostInfo = mysql_fetch_row ($myanswer);
         $PostTime = $PostInfo[1];;
         $PostTime = $PostTime + (7*24*60*60); // 7 days
         $TodayTime = time();
	 print_r ($PostInfo);
         if ($PostTime >= $TodayTime) {
         //   echo "\n--------------------------\n";
         //   echo date("F j, Y, g:i a");
         //   echo "\nCan't it's already there\n";
            return;
        }
      }
   }
      
   // Create the topic
       $smcFunc['db_query']('',"
       INSERT INTO {$db_prefix}topics
       (ID_BOARD, ID_MEMBER_STARTED, ID_MEMBER_UPDATED)
       VALUES ($boardID, $posterInfo[id], $posterInfo[id])", array());

     $topicID = mysql_insert_id();
	 // Create the post
	$smcFunc['db_query']('',"
         INSERT INTO {$db_prefix}messages
         (ID_TOPIC, ID_BOARD, poster_time, ID_MEMBER, subject, poster_name,
           poster_email, poster_ip, body, icon)
      VALUES ( $topicID, $boardID, $timestamp, $posterInfo[id], '$subject',
             '$posterInfo[name]', '$posterInfo[email]', '$posterInfo[ip]',
            '$body', '$icon')",array());
     $msgID = mysql_insert_id();

	 // Update the topic
   $smcFunc['db_query']('',"
         UPDATE {$db_prefix}topics
         SET ID_FIRST_MSG = $msgID, ID_LAST_MSG = $msgID
         WHERE ID_TOPIC = $topicID
        LIMIT 1", array());

// Update the board

   $smcFunc['db_query']('',"
       UPDATE {$db_prefix}boards
       SET ID_LAST_MSG = $msgID, num_topics = num_topics + 1, num_posts = num_posts + 1
       WHERE ID_BOARD = $boardID", array());


// check to see if we are counting posts
   $res = $smcFunc['db_query']('',"
          SELECT count_posts
          FROM {$db_prefix}boards
          WHERE ID_BOARD=$boardID LIMIT 1", array());

    list($countPost) = mysql_fetch_row($res);
    mysql_free_result($res);

	// if so increment
    if ( empty($countPost) )
      $smcFunc['db_query']('',"UPDATE {$db_prefix}members SET posts = posts + 1 WHERE ID_MEMBER=$posterInfo[id]", array());

    $boardIDs = array($boardID);
    //updateLastMessages($boardIDs);
    updateStats('topic');
    updateStats('message');
    return ($topicID);
}
function ForumParseHtml($Story) {
    $Story = str_replace('\\"',"\"", $Story);
    $Story = str_replace('\\"',"\"", $Story);
 return($Story);
}

function fixEncoding($in_str)
{
  $cur_encoding = mb_detect_encoding($in_str) ;
  if($cur_encoding == "UTF-8" && mb_check_encoding($in_str,"UTF-8"))
    return $in_str;
  else
    return utf8_encode($in_str);
} // fixEncoding

?>
