<?
/*
 * This page is used for viewing reports in every viewpoint except auto.
 * It doesn't AJAX grab a new report when you resolve each one, use auto
 * for that (reports.php). If you wanted to add a new view, you'd simply
 * add to the case statement(s) below and add an entry to views.php to 
 * explain it.
 * Any changes made to this page within the foreach() should probably be 
 * replicated on the auto page (reports.php).
 */

if(!check_perms('admin_reports')){
	error(403);
}

include(SERVER_ROOT.'/classes/class_text.php');
$Text = NEW TEXT;

define('REPORTS_PER_PAGE', '10');
list($Page,$Limit) = page_limit(REPORTS_PER_PAGE);


if(isset($_GET['view'])){
	$View = $_GET['view'];
} else {
	error(404);
}

if(isset($_GET['id'])) {
	if(!is_number($_GET['id']) && $View != "type") {
		error(404);
	} else {
		$ID = db_string($_GET['id']);
	}
} else {
	$ID = '';
}
	
$Order = "ORDER BY r.ReportedTime ASC";

if(!$ID) {
	switch($View) {
		case "resolved" :
			$Title = "All the old smelly reports";
			$Where = "WHERE r.Status = 'Resolved'";
			$Order = "ORDER BY r.LastChangeTime DESC";
			break;
		case "unauto" :
			$Title = "New reports (unassigned)";
			$Where = "WHERE r.Status = 'New'";
			break;
		default :
			error(404);
			break;
	}
} else {
	switch($View) {
		case "staff" :
			$DB->query("SELECT Username FROM users_main WHERE ID=".$ID);
			list($Username) = $DB->next_record();
			if($Username) {
				$Title = $Username."'s in progress reports";
			} else {
				$Title = $ID."'s in progress reports";
			}
			$Where = "WHERE r.Status = 'InProgress' AND r.ResolverID = ".$ID;
			break;
		case "resolver" :
			$DB->query("SELECT Username FROM users_main WHERE ID=".$ID);
			list($Username) = $DB->next_record();
			if($Username) {
				$Title = $Username."'s in resolved reports";
			} else {
				$Title = $ID."'s in resolved reports";
			}
			$Where = "WHERE r.Status = 'Resolved' AND r.ResolverID = ".$ID;
			$Order = "ORDER BY r.LastChangeTime DESC";
			break;
		case "group" :
			$Title = "Non resolved reports for the group ".$ID;
			$Where = "WHERE r.Status != 'Resolved' AND tg.ID = ".$ID;
			break;
		case "torrent" :
			$Title = "All reports for the torrent  ".$ID;
			$Where = "WHERE r.TorrentID = ".$ID;
			break;
		case "report" :
			$Title = "Seeing resolution of report ".$ID;
			$Where = "WHERE r.ID = ".$ID;
			break;
		case "reporter" :
			$DB->query("SELECT Username FROM users_main WHERE ID=".$ID);
			list($Username) = $DB->next_record();			
			if($Username) {
				$Title = "All torrents reported by ".$Username;
			} else {
				$Title = "All torrents reported by user ".$ID;
			}
			$Where = "WHERE r.ReporterID = ".$ID;
			$Order = "ORDER BY r.ReportedTime DESC";
			break;
		case "uploader" :
			$DB->query("SELECT Username FROM users_main WHERE ID=".$ID);
			list($Username) = $DB->next_record();			
			if($Username) {
				$Title = "All reports for torrents uploaded by ".$Username;
			} else {
				$Title = "All reports for torrents uploaded by user ".$ID;
			}
			$Where = "WHERE r.Status != 'Resolved' AND t.UserID = ".$ID;
			break;
		case "type":
			$Title = "All New reports for the chosen type";
			$Where = "WHERE r.Status = 'New' AND r.Type = '".$ID."'";
			break;
			break;
		default :
			error(404);
			break;
	}
}



$DB->query("SELECT SQL_CALC_FOUND_ROWS
			r.ID,
			r.ReporterID,
			reporter.Username,
			r.TorrentID,
			r.Type,
			r.UserComment,
			r.ResolverID,
			resolver.Username,
			r.Status,
			r.ReportedTime,
			r.LastChangeTime,
			r.ModComment,
			r.Track,
			r.Image,
			r.ExtraID,
			r.Link,
			r.LogMessage,
			tg.Name,
			tg.ID,
			t.Time,
			t.Size,
			t.UserID AS UploaderID,
			uploader.Username
			FROM reportsv2 AS r
			LEFT JOIN torrents AS t ON t.ID=r.TorrentID
			LEFT JOIN torrents_group AS tg ON tg.ID=t.GroupID
			LEFT JOIN users_main AS resolver ON resolver.ID=r.ResolverID
			LEFT JOIN users_main AS reporter ON reporter.ID=r.ReporterID
			LEFT JOIN users_main AS uploader ON uploader.ID=t.UserID "
			.$Where."
			GROUP BY r.ID " 
			.$Order."
			LIMIT ".$Limit);

$Reports = $DB->to_array();

$DB->query('SELECT FOUND_ROWS()');
list($Results) = $DB->next_record();
$PageLinks=get_pages($Page,$Results,REPORTS_PER_PAGE,11);

show_header('Reports V2!', 'reportsv2,bbcode,inbox,reports,jquery');
include('header.php');

?>
<div class="thin">
<h2><?=$Title?></h2>
<div class="buttonbox thin center">
	<? if($View != "resolved") { ?>
		<span title="Resolves *all* checked reports with their respective resolutions"><input type="button" onclick="MultiResolve();" value="Multi-Resolve" /></span>
		<span title="Assigns all of the reports on the page to you!"><input type="button" onclick="Grab();" value="Grab All" /></span>
	<? } ?>
	<? if($View == "staff" && $LoggedUser['ID'] == $ID) { ?>| <span title="Un-In Progress all the reports currently displayed"><input type="button" onclick="GiveBack();" value="Give back all" /></span><? } ?>
</div>
<br />
<div class="linkbox">
<?=$PageLinks?>
</div>
<div id="all_reports" >

<?
if(count($Reports) == 0) {
?>
	<div>
		<table>
			<tr>
				<td class='center'>
					<strong>No new reports! \o/</strong>
				</td>
			</tr>
		</table>
	</div>
<?
} else {
	foreach($Reports as $Report) {
		
		
		list($ReportID, $ReporterID, $ReporterName, $TorrentID, $Type, $UserComment, $ResolverID, $ResolverName, $Status, $ReportedTime, $LastChangeTime, 
			$ModComment, $Tracks, $Images, $ExtraIDs, $Links, $LogMessage, $GroupName, $GroupID, $Time, 
			$Size, $UploaderID, $UploaderName) = display_array($Report, array("ModComment"));
		
		if(!$GroupID && $Status != "Resolved") {
			//Torrent already deleted
			$DB->query("UPDATE reportsv2 SET
			Status='Resolved',
			LastChangeTime='".sqltime()."',
			ModComment='Report already dealt with (Torrent deleted)'
			WHERE ID=".$ReportID);
			$Cache->decrement('num_torrent_reportsv2');
?>
	<div id=report<?=$ReportID?>>
		<table>
			<tr>
				<td class='center'>
					<a href="reportsv2.php?view=report&amp;id=<?=$ReportID?>">Report <?=$ReportID?></a> for torrent <?=$TorrentID?> (deleted) has been automatically resolved. <input type="button" value="Hide" onclick="ClearReport(<?=$ReportID?>);" />
				</td>
			</tr>
		</table>
	</div>
<?
		} else { 
                        if (array_key_exists($Type, $Types)) {
                              $ReportType = $Types[$Type];
                        } else {
                              //There was a type but it wasn't an option!
                              $Type = 'other';
                              $ReportType = $Types['other'];
                        }
                        $RawName = $GroupName." (".get_size($Size).")" ; // number_format($Size/(1024*1024), 2)." MB)";
                        $LinkName = "<a href='torrents.php?id=$GroupID'>$GroupName</a> (".get_size($Size).")" ; // number_format($Size/(1024*1024), 2)." MB)";
                        $BBName = "[url=torrents.php?id=$GroupID]$GroupName"."[/url] (".get_size($Size).")" ; // number_format($Size/(1024*1024), 2)." MB)";
                        
		?>	
			<div id="report<?=$ReportID?>" class="reports">
				<form id="report_form<?=$ReportID?>" action="reportsv2.php" method="post">
					<? 
						/*
						* Some of these are for takeresolve, namely the ones that aren't inputs, some for the javascript.			
						*/
					?>
					<div>
						<input type="hidden" name="auth" value="<?=$LoggedUser['AuthKey']?>" />
						<input type="hidden" id="newreportid" name="newreportid" value="<?=$ReportID?>" />
						<input type="hidden" id="reportid<?=$ReportID?>" name="reportid" value="<?=$ReportID?>" />
						<input type="hidden" id="torrentid<?=$ReportID?>" name="torrentid" value="<?=$TorrentID?>" />
						<input type="hidden" id="uploader<?=$ReportID?>" name="uploader" value="<?=$UploaderName?>" />
						<input type="hidden" id="uploaderid<?=$ReportID?>" name="uploaderid" value="<?=$UploaderID?>" />
						<input type="hidden" id="reporterid<?=$ReportID?>" name="reporterid" value="<?=$ReporterID?>" />
						<input type="hidden" id="report_reason<?=$ReportID?>" name="report_reason" value="<?=$UserComment?>" />
						<input type="hidden" id="raw_name<?=$ReportID?>" name="raw_name" value="<?=$RawName?>" />
						<input type="hidden" id="type<?=$ReportID?>" name="type" value="<?=$Type?>" />
                        <input type="hidden" id="from_delete<?=$ReportID?>" name="from_delete" value="0" />
					</div>
					<table cellpadding="5">
						<tr>
							<td class="label"><a href="reportsv2.php?view=report&amp;id=<?=$ReportID?>">Reported </a>Torrent:</td>
							<td colspan="3">
								<!--<div style="text-align: right;">was reported by <a href="user.php?id=<?=$ReporterID?>"><?=$ReporterName?></a> <?=time_diff($ReportedTime)?> for the reason: <strong><?=$ReportType['title']?></strong></div>-->
			<?	if(!$GroupID) { ?>
								<a href="log.php?search=Torrent+<?=$TorrentID?>"><?=$TorrentID?></a> (Deleted)
			<?  } else {?>
								<?=$LinkName?>
								<a href="torrents.php?action=download&amp;id=<?=$TorrentID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" title="Download">[DL]</a>
								uploaded by <a href="user.php?id=<?=$UploaderID?>"><?=$UploaderName?></a> <?=time_diff($Time)?>
								<br />
			<?	if($Status != 'Resolved') {
				
					$DB->query("SELECT r.ID 
								FROM reportsv2 AS r 
								LEFT JOIN torrents AS t ON t.ID=r.TorrentID 
								WHERE r.Status != 'Resolved'
								AND t.GroupID=$GroupID");
					$GroupOthers = ($DB->record_count() - 1);
					
					if($GroupOthers > 0) { ?>
								<div style="text-align: right;">
									<a href="reportsv2.php?view=group&amp;id=<?=$GroupID?>">There <?=(($GroupOthers > 1) ? "are $GroupOthers other reports" : "is 1 other report")?> for torrent(s) in this group</a>
								</div>
			<? 		}
					
					$DB->query("SELECT t.UserID 
								FROM reportsv2 AS r 
								JOIN torrents AS t ON t.ID=r.TorrentID 
								WHERE r.Status != 'Resolved'
								AND t.UserID=$UploaderID");
					$UploaderOthers = ($DB->record_count() - 1);
	
					if($UploaderOthers > 0) { ?>
								<div style="text-align: right;">
									<a href="reportsv2.php?view=uploader&amp;id=<?=$UploaderID?>">There <?=(($UploaderOthers > 1) ? "are $UploaderOthers other reports" : "is 1 other report")?> for torrent(s) uploaded by this user</a>
								</div>
			<? 		}
				
					$DB->query("SELECT DISTINCT req.ID,
								req.FillerID,
								um.Username,
								req.TimeFilled
								FROM requests AS req 
								LEFT JOIN torrents AS t ON t.ID=req.TorrentID
								LEFT JOIN reportsv2 AS rep ON rep.TorrentID=t.ID
								JOIN users_main AS um ON um.ID=req.FillerID
								WHERE rep.Status != 'Resolved'
								AND req.TimeFilled > '2010-03-04 02:31:49'
								AND req.TorrentID = $TorrentID");
					$Requests = ($DB->record_count());
					if($Requests > 0) { 
						while(list($RequestID, $FillerID, $FillerName, $FilledTime) = $DB->next_record()) {
				?>
									<div style="text-align: right;">
										<a href="user.php?id=<?=$FillerID?>"><?=$FillerName?></a> used this torrent to fill <a href="requests.php?action=view&amp;id=<?=$RequestID?>">this request</a> <?=time_diff($FilledTime)?>
									</div>
				<?		}
					}
				}
			}
				?>
							</td>
						</tr>
						<tr>
							<td class="label">Reported By:</td>
							<td colspan="3">
                                <a href="user.php?id=<?=$ReporterID?>"><?=$ReporterName?></a> <?=time_diff($ReportedTime)?> for the reason: <strong><?=$ReportType['title']?></strong>
							</td>
						</tr>
			<? if($Tracks) { ?>
						<tr>
							<td class="label">Relevant Tracks:</td>
							<td colspan="3">
								<?=str_replace(" ", ", ", $Tracks)?>
							</td>
						</tr>
			<? }
			
				if($Links) {
			?>
						<tr>
							<td class="label">Relevant Links:</td>
							<td colspan="3">
			<?
					$Links = explode(" ", $Links);
					foreach($Links as $Link) {
					
						if ($local_url = $Text->local_url($Link)) {
							$Link = $local_url;
						}
			?>
								<a href="<?=$Link?>"><?=$Link?></a>
			<?
					}
			?>
							</td>
						</tr>
			<?
				}
			
				if($ExtraIDs) {
			?>
						<tr>
							<td class="label">Relevant Other Torrents:</td>
							<td colspan="3">
                                <input class="hidden" name="extras_id" value="<?=$ExtraIDs?>" />
                        
			<?
					$First = true;
					$Extras = explode(" ", $ExtraIDs);
					foreach($Extras as $ExtraID) {


						$DB->query("SELECT 
									tg.Name,
									tg.ID,
									t.Time,
									t.Size,
									t.UserID AS UploaderID,
									uploader.Username
									FROM torrents AS t
									LEFT JOIN torrents_group AS tg ON tg.ID=t.GroupID
									LEFT JOIN users_main AS uploader ON uploader.ID=t.UserID
									WHERE t.ID='$ExtraID'
									GROUP BY tg.ID");
						
						list($ExtraGroupName, $ExtraGroupID, $ExtraTime, 
							$ExtraSize, $ExtraUploaderID, $ExtraUploaderName) = display_array($DB->next_record());
						
						if($ExtraGroupName) {
                                                    $ExtraLinkName = "<a href='torrents.php?id=$ExtraGroupID'>$ExtraGroupName</a> (". get_size($ExtraSize).")"; // number_format($ExtraSize/(1024*1024), 2)." MB)";
?>
									<?=($First ? "" : "<br />")?>
									<?=$ExtraLinkName?>
									<a href="torrents.php?action=download&amp;id=<?=$ExtraID?>&amp;authkey=<?=$LoggedUser['AuthKey']?>&amp;torrent_pass=<?=$LoggedUser['torrent_pass']?>" title="Download">[DL]</a>
									uploaded by <a href="user.php?id=<?=$ExtraUploaderID?>"><?=$ExtraUploaderName?></a>  <?=time_diff($ExtraTime)?> [<a title="Close this report and create a new dupe report with this torrent as the reported one" href="#" onclick="Switch(<?=$ReportID?>, <?=$ReporterID?>, '<?=$UserComment?>', <?=$TorrentID?>, <?=$ExtraID?>); return false;">Switch</a>]
				<?
							$First = false;
						}
					} 
			?>
							</td>
						</tr>
			<?
				}
				
				if($Images) {
			?>
						<tr>
							<td class="label">Relevant Images:</td>
							<td colspan="3">
			<?
					$Images = explode(" ", $Images);
					foreach($Images as $Image) {
			?>
								<img style="max-width: 200px;" onclick="lightbox.init(this,200);" src="<?=$Image?>" alt="<?=$Image?>" />	
			<? 
					}
			?>
							</td>
						</tr>
			<? 
				}
			?>
						<tr>
							<td class="label">User Comment:</td>
							<td colspan="3"><?=$Text->full_format($UserComment)?></td>
						</tr>
						<? // END REPORTED STUFF :|: BEGIN MOD STUFF ?>
			<?	
				if($Status == "InProgress") {
			?>
						<tr>
							<td class="label">In Progress by:</td>
							<td colspan="3">
								<a href="user.php?id=<?=$ResolverID?>"><?=$ResolverName?></a>
							</td>
						</tr>
			<?	}
				if($Status != "Resolved") { 
			?>
						<tr>
							<td class="label">Report Comment:</td>
							<td colspan="3">
								<input type="text" name="comment" id="comment<?=$ReportID?>" size="45" value="<?=$ModComment?>" />
								<input type="button" value="Update now" onclick="UpdateComment(<?=$ReportID?>)" />
							</td>
						</tr>
						<tr class="spacespans">
							<td class="label">
								<a href="javascript:Load('<?=$ReportID?>')" title="Set back to <?=$ReportType['title']?>">Resolve</a>
							</td>
							<td colspan="3">
								<select name="resolve_type" id="resolve_type<?=$ReportID?>" onchange="ChangeResolve(<?=$ReportID?>)">
	<?
		$TypeList = $Types;
		$Priorities = array();
		foreach ($TypeList as $Key => $Value) {
			$Priorities[$Key] = $Value['priority'];
		}
		array_multisort($Priorities, SORT_ASC, $TypeList);
	
		foreach($TypeList as $Type => $Data) {
	?>
							<option value="<?=$Type?>"><?=$Data['title']?></option>
	<? } ?>
								</select>
								<span id="options<?=$ReportID?>">
<? if(check_perms('users_mod')) { ?>
									<span title="Delete Torrent?">	
										<strong>Delete</strong>
										<input type="checkbox" name="delete" id="delete<?=$ReportID?>"/>
									</span>
<? } ?>
									<span title="Warning length in weeks">
										<strong>Warning</strong>
										<select name="warning" id="warning<?=$ReportID?>">
									<option value="0">none</option>
									<option value="1">1 week</option>
<?                                      for($i = 2; $i < 9; $i++) {  ?>
									<option value="<?=$i?>"><?=$i?> weeks</option>
<?                                      }       ?>
										</select>
									</span>
									<span title="Remove upload privileges?">
										<strong>Disable Upload</strong>
										<input type="checkbox" name="upload" id="upload<?=$ReportID?>"/>
									</span>
<?                                      //if ($ReportType['resolve_options']['bounty'] != '0') {  ?>
                                    <span title="Pay bounty to reporter (<?=$ReporterName?>)">
                                        <strong>Pay Bounty (<span id="bounty_amount<?=$ReportID?>"><?=$ReportType['resolve_options']['bounty']?></span>)</strong>
                                        <input type="checkbox" name="bounty" id="bounty<?=$ReportID?>"/>
                                    </span>
<?                                      //}       ?>
									<span title="Change report type / resolve action">
										<input type="button" name="update_resolve" id="update_resolve<?=$ReportID?>" value="Change report type" onclick="UpdateResolve(<?=$ReportID?>)" />
									</span>
								</span>
								</td>
						</tr>
						<tr>
							<td class="label">
								PM
								<select name="pm_type" id="pm_type<?=$ReportID?>">
									<option value="Uploader">Uploader</option>
									<option value="Reporter">Reporter</option>
								</select>
							</td> 
							<td colspan="3">A PM is automatically generated for the uploader (and if a bounty is paid to the reporter). Any text here is appended to the uploaders auto PM unless using 'Send Now' to immediately send a message.<br />
                                <blockquote><strong>uploader pm text:</strong><br/><span id="pm_message<?=$ReportID?>"><?=$ReportType['resolve_options']['pm']?></span></blockquote>
                                <span title="Uploader: Appended to the regular message unless using send now. Reporter: Must be used with send now">
									<textarea name="uploader_pm" id="uploader_pm<?=$ReportID?>" cols="50" rows="1"></textarea>
								</span>
								<input type="button" value="Send Now" onclick="SendPM(<?=$ReportID?>)" />
							</td>
						</tr>
						<tr>
							<td class="label"><strong>Extra</strong> Log Message:</td> 
							<td>
								<input type="text" name="log_message" id="log_message<?=$ReportID?>" class="long" <? if($ExtraIDs) {
											$Extras = explode(" ", $ExtraIDs);
											$Value = "";
											foreach($Extras as $ExtraID) {
												$Value .= 'http://'.NONSSL_SITE_URL.'/torrents.php?torrentid='.$ExtraID.' ';
											}
											echo 'value="'.trim($Value).'"';
										} ?>/>
							</td>
							<td class="label"><strong>Extra</strong> Staff Notes:</td> 
							<td>
								<input type="text" name="admin_message" id="admin_message<?=$ReportID?>" class="long" />
							</td>
						</tr>
						<tr>
							<td colspan="4" style="text-align: center;">
								<input type="button" value="Invalid Report" onclick="Dismiss(<?=$ReportID?>);" title="Dismiss this as an invalid Report" />
								<input type="button" value="Report resolved manually" onclick="ManualResolve(<?=$ReportID?>);" title="Set status to Resolved but take no automatic action" />
			<?		if($Status == "InProgress" && $LoggedUser['ID'] == $ResolverID) { ?>
								| <input type="button" value="Give back" onclick="GiveBack(<?=$ReportID?>);" />
			<? 		} else { ?>
								| <input id="grab<?=$ReportID?>" type="button" value="Grab!" onclick="Grab(<?=$ReportID?>);" />
			<?		}	?>
								| <span  title="If checked then include when multi-resolving">Multi-Resolve <input type="checkbox" name="multi" id="multi<?=$ReportID?>" checked="checked" /></span>
								| <input type="button" id="submit_<?=$ReportID?>" value="Resolve Report" onclick="TakeResolve(<?=$ReportID?>);" title="Resolve Report (carry out whatever actions are set)" />
							</td>
						</tr>
                             <?
                          
                // get the conversations
                $Conversations = array();
                $DB->query("SELECT rc.ConvID, pm.UserID, um.Username, 
                                (CASE WHEN UserID='$ReporterID' THEN 'Reporter' 
                                      WHEN UserID='$UploaderID' THEN 'Offender'
                                      ELSE 'other' 
                                 END) AS ConvType, pm.Date
                                FROM reportsv2_conversations AS rc 
                                JOIN staff_pm_conversations AS pm ON pm.ID=rc.ConvID
                                LEFT JOIN users_main AS um ON um.ID=pm.UserID
                            WHERE ReportID=" . $ReportID . "
                                ORDER BY pm.Date ASC");
                $Conversations = $DB->to_array();
                
                if (count($Conversations)>0) { 
                ?>
                    <tr class="rowa">
                        <td colspan="5" style="border-right: none">
                            <? 
                            foreach ($Conversations as $Conv) {  // if conv has already been started just provide a link to it
                                list($cID, $cUserID, $cUsername, $cType, $cDate)=$Conv;
                                ?>
                                <div style="text-align: right;">
                                    <em>(<?=  time_diff($cDate)?>)</em> &nbsp;view existing conversation with <a href="user.php?id=<?= $cUserID ?>"><?= $cUsername ?></a> (<?=$cType?>) about this report: &nbsp;&nbsp
                                    <a href="staffpm.php?action=viewconv&id=<?= $cID ?>" target="_blank">[View Message]</a> &nbsp;
                                </div>
                                <? 
                            }
                            ?>
                        </td>
                    </tr>
                <?
                }  
                             
                             
                              
                             ?>
                        <tr>
                            <td colspan="4">
                                
                                <span style="float:right;">
                                    Start staff conversation with <select name="toid" >
                                        <option value="<?=$UploaderID?>"><?=$UploaderName?> (Uploader)</option>
									<option value="<?=$ReporterID?>"><?=$ReporterName?> (Reporter)</option>
								</select> about this report: &nbsp;&nbsp;&nbsp;&nbsp;
                                    <a href="#report<?= $ReportID ?>" onClick="Open_Compose_Message(<?="'$ReportID'"?>)">[Compose Message]</a>
                                </span>    
                                
								
                                <br class="clear" />
                                <div id="compose<?= $ReportID ?>" class="hide">
                                    <div id="preview<?= $ReportID ?>" class="hidden"></div>
                                    <div id="common_answers<?= $ReportID ?>" class="hidden">
                                        <div class="box vertical_space">
                                            <div class="head">
                                                <strong>Preview</strong>
                                            </div>
                                            <div id="common_answers_body<?= $ReportID ?>" class="body">Select an answer from the dropdown to view it.</div>
                                        </div>
                                        <br />
                                        <div class="center">
                                            <select id="common_answers_select<?= $ReportID ?>" onChange="Update_Message(<?= $ReportID ?>);">
                                                <option id="first_common_response<?= $ReportID ?>">Select a message</option>
                                                <?
                                                // List common responses
                                                $DB->query("SELECT ID, Name FROM staff_pm_responses");
                                                while (list($crID, $crName) = $DB->next_record()) {
                                                    ?>
                                                    <option value="<?= $crID ?>"><?= $crName ?></option>
                                                <? } ?>
                                            </select>
                                            <input type="button" value="Set message" onClick="Set_Message(<?=$ReportID?>);" />
                                            <input type="button" value="Create new / Edit" onClick="location.href='staffpm.php?action=responses&convid=<?= $ConvID ?>'" />
                                        </div>
                                    </div>
                                    <!--<form action="reports.php" method="post" id="messageform<?= $ReportID ?>">-->
                                        <div id="quickpost<?= $ReportID ?>"> 
                                            <!-- <input type="hidden" name="reportid" value="<?= $ReportID ?>" /> 
                                            <input type="hidden" name="username" value="<?= $Username ?>" />
                                            <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" /> 
                                             <input type="hidden" name="action" value="takepost" />  -->
                                           
                                            <input type="hidden" name="prependtitle" value="Staff PM - " />

                                            <label for="subject"><h3>Subject</h3></label>
                                            <input class="long" type="text" name="subject" id="subject<?= $ReportID ?>" value="<?= display_str($Subject) ?>" />
                                            <br />

                                            <label for="message"><h3>Message</h3></label>
                                            <? $Text->display_bbcode_assistant("message$ReportID"); ?>
                                            <textarea rows="6" class="long" name="message" id="message<?= $ReportID ?>"><?= display_str($Message) ?></textarea>
                                            <br />

                                        </div>
                                        <input type="button" value="Hide" onClick="jQuery('#compose<?= $ReportID ?>').toggle();return false;" />
                                     
                                        <input type="button" id="previewbtn<?= $ReportID ?>" value="Preview" onclick="Inbox_Preview(<?= "'$ReportID'" ?>);" /> 

                                        <input type="button" value="Common answers" onClick="$('#common_answers<?= $ReportID ?>').toggle();" />
                                        <input type="submit" name="sendmessage" value="Send message to selected user" />

                                    <!--</form>-->
                                </div>
                            </td>
                        </tr>   
			<?  
				} else {
			?>
						<tr>
							<td class="label">Resolver</td> 
							<td colspan="3">
								<a href="user.php?id=<?=$ResolverID?>"><?=$ResolverName?></a>
							</td>
						</tr>
						<tr>
							<td class="label">Resolve Time</td> 
							<td colspan="3">
								<?=time_diff($LastChangeTime)?>
							</td>
						</tr>
						<tr>
							<td class="label">Report Comments</td> 
							<td colspan="3">
								<?=$ModComment?>
							</td>
						</tr>
						<tr>
							<td class="label">Log Message</td> 
							<td colspan="3">
								<?=$LogMessage?>
							</td>
						</tr>
			<? if($GroupID) { ?>
						<tr>
							<td	colspan="4" style="text-align: center;">
								<input id="grab<?=$ReportID?>" type="button" value="Grab!" onclick="Grab(<?=$ReportID?>);" />
							</td>
						</tr>
			<?	}
            // get the conversations
                $Conversations = array();
                $DB->query("SELECT rc.ConvID, pm.UserID, um.Username, 
                                (CASE WHEN UserID='$ReporterID' THEN 'Reporter' 
                                      WHEN UserID='$UploaderID' THEN 'Offender'
                                      ELSE 'other' 
                                 END) AS ConvType, pm.Date
                                FROM reportsv2_conversations AS rc 
                                JOIN staff_pm_conversations AS pm ON pm.ID=rc.ConvID
                                LEFT JOIN users_main AS um ON um.ID=pm.UserID
                            WHERE ReportID=" . $ReportID . "
                                ORDER BY pm.Date ASC");
                $Conversations = $DB->to_array();
                
                if (count($Conversations)>0) { 
                ?>
                    <tr class="rowa">
                        <td colspan="5" style="border-right: none">
                            <? 
                            foreach ($Conversations as $Conv) {  // if conv has already been started just provide a link to it
                                list($cID, $cUserID, $cUsername, $cType, $cDate)=$Conv;
                                ?>
                                <div style="text-align: right;">
                                    <em>(<?=  time_diff($cDate)?>)</em> &nbsp;view existing conversation with <a href="user.php?id=<?= $cUserID ?>"><?= $cUsername ?></a> (<?=$cType?>) about this report: &nbsp;&nbsp
                                    <a href="staffpm.php?action=viewconv&id=<?= $cID ?>" target="_blank">[View Message]</a> &nbsp;
                                </div>
                                <? 
                            }
                            ?>
                        </td>
                    </tr>
                <?
                }
            
            
            ?>
                   
                        
<?          }
			?>
					</table>
				</form>
				<br />
			</div>
			<script type="text/javascript">Load('<?=$ReportID?>');</script>
		<?
		}
	}
}
?>
</div>
<div class="linkbox">
	<?=$PageLinks?>
</div>
</div>
<?
show_footer();
?>
