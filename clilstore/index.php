<?php
  if (!include('autoload.inc.php'))
    header("Location:http://claran.smo.uhi.ac.uk/mearachd/include_a_dhith/?faidhle=autoload.inc.php");

  header('Cache-Control: no-cache, no-store, must-revalidate');
  header("Cache-Control:max-age=0");

  try {
    $myCLIL = SM_myCLIL::singleton();
    $user = ( isset($myCLIL->id) ? $myCLIL->id : '' );
    $csSess   = SM_csSess::singleton();
    $DbMultidict = SM_DbMultidictPDO::singleton('rw');
    $servername = $_SERVER['SERVER_NAME'];

//    if (isset($_GET['mode']))         { $csSess->setMode($_GET['mode']            ); }
    if (!empty($_GET['sortCol']))     { $csSess->sortCol($_GET['sortCol']         ); }
//    if (!empty($_GET['deleteCol']))   { $csSess->deleteCol($_GET['deleteCol']     ); }
    if (!empty($_GET['addCol']))      { $csSess->addCol($_GET['addCol']           ); }
    if (!empty($_GET['restoreCols'])) { $csSess->restoreCols($_GET['restoreCols'] ); }

    $mode    = $csSess->getCsSession()->mode;
    $filterForm = ( isset($_REQUEST['filterForm']) ? 1 : 0 );
    if ($filterForm && $mode>1) {
       if (isset($_REQUEST['incTest'])) { $csSess->setIncTest(1); } else { $csSess->setIncTest(0); }
       if (isset($_REQUEST['wide']))    { $csSess->setMode(3);    } else { $csSess->setMode(2);    }
    }

    $mode    = $csSess->getCsSession()->mode;
    $incTest = $csSess->getCsSession()->incTest;

    $mode0selected = ( $mode==0 ? 'selected' : '');
    $mode1selected = ( $mode==1 ? 'selected' : '');
    $mode2selected = ( $mode==2 ? 'selected' : '');
    $mode3selected = ( $mode==3 ? 'selected' : '');
    $addColHtml = $csSess->addColHtml();
    $symbolRowHtml = $csSess->symbolRowHtml();

    $studentphoto = $photo = $tabletopChoices = '';
    if ($mode==0) { $studentphoto = '<img src="student.jpg" alt="">'; }
    if ($mode==1) { $photo = '<img src="http://upload.wikimedia.org/wikipedia/commons/thumb/b/b3/Headphones-Sennheiser-HD555.jpg/320px-Headphones-Sennheiser-HD555.jpg" '
                                . 'style="float:left;padding-left:20px;width:80px;height:60px" alt="">'; }

    if ($mode<=1) { $checkboxesHtml = ''; } else {
        $incTestLabel = ( empty($user)
                        ? 'Include test units'
                        : 'Include test units by other authors' );
        $incTestChecked = ( $incTest ? 'checked' : '' );
        $wideChecked    = ( $mode==3 ? 'checked' : '' );
        $checkboxesHtml = <<<CHECKBOXES
<div style="color:grey;font-size:90%;margin-left:1em">
<input type="checkbox" name="incTest" id="incTest" $incTestChecked tabindex=2 title="include test units" onclick="submitFForm()">
<label for="incTest" style="padding-right:2em">$incTestLabel</label>
<input type="checkbox" name="wide" id="wide" $wideChecked tabindex=4 title="include all columns" onclick="submitFForm()">
<label for="wide">More options</label>
</div>
CHECKBOXES;
    }

    if ($mode<=1) {
        $userHtml = '<br><p style="clear:both;font-size:80%">Select the language you are learning and your level to see the available units.</p>';
    } elseif (empty($user)) {
        $userHtml = <<<USER1
<br><p style="clear:both"><a href="login.php">Login</a> or <a href="register.php">register</a> if you wish to create and edit pages.</p>
USER1;
    } else {
        $userHtml = <<<USER2
<div style="clear:both;float:left;margin:0.5em 0 1.8em 0;padding:2px 4px;background-color:#def">
<p style="margin:2px 3px">Logged in as <b>$user</b>
<a href="logout.php" title="Logout from Clilstore" class="mybutton" style="margin-right:5px">Logout <img src="/icons-smo/logout.png" alt=""></a>
<a href="options.php?user=$user" title="Change your Clilstore options or password" class="mybutton" style="margin-right:2.5em">Options</a>
<a href="./?owner=$user" class="mybutton" style="margin-left:0;margin-right:1px">My units</a>
<a href="edit.php?id=0" class="mybutton" style="margin-right:2px">Create a unit…</a>
</p>
</div><br style="clear:both">
USER2;
    }

    function wildChars (&$s,&$sVis,$con) {
     //Standardises wildcard characters to SQL format (% and _), removing duplicates
     //Adds a wildcard at the end if $con='starts', and at the start if $con='contains'
     //Sets the visible parameter $sVis appropriately (using * and ? instead of % and _)
        if (empty($s)) { $sVis = ''; return; }
        if ($con=='starts')   { $s =       $s . '%'; }
        if ($con=='contains') { $s = '%' . $s . '%'; }
        $s    = strtr( $s, array('*'=>'%','?'=>'_') );
        $s    = strtr( $s, array('%_'=>'_%')        );
        $s    = strtr( $s, array('%%'=>'%')         );
        $s    = strtr( $s, array('%%'=>'%')         );
        $sVis = strtr( $s, array('%'=>'*','_'=>'?') );
        if ($con=='starts'
         || $con=='contains') { $sVis = substr($sVis,0,-1); }
        if ($con=='contains') { $sVis = substr($sVis,1);    }
    }

    $stmt = $DbMultidict->prepare('SELECT DISTINCT owner FROM clilstore ORDER BY owner');
    $stmt->execute();
    $ownerList = $stmt->fetchAll(PDO::FETCH_COLUMN,0);
    foreach ($ownerList as &$owner) { $owner = "<option value=\"$owner\">"; }
    $ownerListHtml = implode("\n",$ownerList);

    $f['idFil']      =
    $f['viewsMin']   =
    $f['viewsMax']   =
    $f['clicksMin']  =
    $f['clicksMax']  =
    $f['createdMin'] =
    $f['createdMax'] =
    $f['changedMin'] =
    $f['changedMax'] =
    $f['licenceFil'] =
    $f['ownerFil']   =
    $f['slFil']      =
    $f['levelMin']   =
    $f['levelMax']   =
    $f['wordsMin']   =
    $f['wordsMax']   =
    $f['medtypeFil'] =
    $f['medlenMin']  =
    $f['medlenMax']  =
    $f['buttonsMin']  =
    $f['buttonsMax']  =
    $f['filesMin']  =
    $f['filesMax']  =
    $f['titleFil']   =
    $f['textFil']    = '';

    if (empty($_GET)) { $csSess->getFilter($f); }  //if we have no GET parameters, restore stored filter values
    elseif ( count($_GET)==1                       //and do the same if we have just a single GET parameter consisting of one of the commands mode..levelBut
          && in_array( array_keys($_GET)[0], array('mode','sortCol','deleteCol','addCol','restoreCols','levelBut'))
           ) { $csSess->getFilter($f); }

    if (isset($_REQUEST['id']))         { $f['idFil']      = $_REQUEST['id'];         }
    if (isset($_REQUEST['viewsMin']))   { $f['viewsMin']   = $_REQUEST['viewsMin'];   }
    if (isset($_REQUEST['viewsMax']))   { $f['viewsMax']   = $_REQUEST['viewsMax'];   }
    if (isset($_REQUEST['clicksMin']))  { $f['clicksMin']  = $_REQUEST['clicksMin'];  }
    if (isset($_REQUEST['clicksMax']))  { $f['clicksMax']  = $_REQUEST['clicksMax'];  }
    if (isset($_REQUEST['createdMin'])) { $f['createdMin'] = $_REQUEST['createdMin']; }
    if (isset($_REQUEST['createdMax'])) { $f['createdMax'] = $_REQUEST['createdMax']; }
    if (isset($_REQUEST['changedMin'])) { $f['changedMin'] = $_REQUEST['changedMin']; }
    if (isset($_REQUEST['changedMax'])) { $f['changedMax'] = $_REQUEST['changedMax']; }
    if (isset($_REQUEST['licence']))    { $f['licenceFil'] = $_REQUEST['licence'];    }
    if (isset($_REQUEST['owner']))      { $f['ownerFil']   = $_REQUEST['owner'];      }
    if (isset($_REQUEST['sl']))         { $f['slFil']      = $_REQUEST['sl'];         }
    if (isset($_REQUEST['levelMin']))   { $f['levelMin']   = $_REQUEST['levelMin'];   }
    if (isset($_REQUEST['levelMax']))   { $f['levelMax']   = $_REQUEST['levelMax'];   }
    if (isset($_REQUEST['wordsMin']))   { $f['wordsMin']   = $_REQUEST['wordsMin'];   }
    if (isset($_REQUEST['wordsMax']))   { $f['wordsMax']   = $_REQUEST['wordsMax'];   }
    if (isset($_REQUEST['medtype']))    { $f['medtypeFil'] = $_REQUEST['medtype'];    }
    if (isset($_REQUEST['medlenMin']))  { $f['medlenMin']  = $_REQUEST['medlenMin'];  }
    if (isset($_REQUEST['medlenMax']))  { $f['medlenMax']  = $_REQUEST['medlenMax'];  }
    if (isset($_REQUEST['buttonsMin'])) { $f['buttonsMin'] = $_REQUEST['buttonsMin']; }
    if (isset($_REQUEST['buttonsMax'])) { $f['buttonsMax'] = $_REQUEST['buttonsMax']; }
    if (isset($_REQUEST['filesMin']))   { $f['filesMin']   = $_REQUEST['filesMin'];   }
    if (isset($_REQUEST['filesMax']))   { $f['filesMax']   = $_REQUEST['filesMax'];   }
    if (isset($_REQUEST['title']))      { $f['titleFil']   = $_REQUEST['title'];      }
    if (isset($_REQUEST['text']))       { $f['textFil']    = $_REQUEST['text'];       }

    if ($mode==0) {  // Keep things really simple for mode 0, basic student mode
        $f['idFil']      =
        $f['viewsMin']   =
        $f['viewsMax']   =
        $f['clicksMin']  =
        $f['clicksMax']  =
        $f['createdMin'] =
        $f['createdMax'] =
        $f['changedMin'] =
        $f['changedMax'] =
        $f['licenceFil'] =
        $f['ownerFil']   =
        $f['wordsMin']   =
        $f['wordsMax']   =
        $f['medtypeFil'] =
        $f['medlenMin']  =
        $f['medlenMax']  =
        $f['buttonsMin'] =
        $f['buttonsMax'] =
        $f['filesMin']   =
        $f['filesMax']   =
//        $f['titleFil']   =
//        $f['textFil']    =
        '';
        if (empty($f['slFil'])) { $csSess->csFilter['sl']['m0'] = 1; }
         else                   { $csSess->csFilter['sl']['m0'] = 0; }  // No need to display Language if it is being filtered for
       // Set up checked values for level radio buttons
        $level = $csSess->csFilter['level']['val1'];
        if ($level==='') {
            $levelAnychecked = 'checked';
        } else {
            $levelAnychecked = '';
            $levelA1checked = ( $level== 0 ? 'checked' : '');
            $levelA2checked = ( $level==10 ? 'checked' : '');
            $levelB1checked = ( $level==20 ? 'checked' : '');
            $levelB2checked = ( $level==30 ? 'checked' : '');
            $levelC1checked = ( $level==40 ? 'checked' : '');
            $levelC2checked = ( $level==50 ? 'checked' : '');
        }
    }

    $idDisplay      = $csSess->display('id');
    $viewsDisplay   = $csSess->display('views');
    $clicksDisplay  = $csSess->display('clicks');
    $createdDisplay = $csSess->display('created');
    $changedDisplay = $csSess->display('changed');
    $licenceDisplay = $csSess->display('licence');
    $ownerDisplay   = $csSess->display('owner');
    $slDisplay      = $csSess->display('sl');
    $levelDisplay   = $csSess->display('level');
    $wordsDisplay   = $csSess->display('words');
    $medtypeDisplay = $csSess->display('medtype');
    $medlenDisplay  = $csSess->display('medlen');
    $buttonsDisplay = $csSess->display('buttons');
    $filesDisplay   = $csSess->display('files');
    $titleDisplay   = $csSess->display('title');

    if ($mode==3) {
        $deleteDisplay = 'table-cell';
        $editDisplay   = 'table-cell';
        $nowlDisplay   = 'table-cell';
    } elseif ($mode==2) {
        $deleteDisplay = 'table-cell';
        $editDisplay   = 'table-cell';
        $nowlDisplay   = 'none';
    } else {
        $deleteDisplay = 'none';
        $editDisplay   = 'none';
        $nowlDisplay   = 'none';
    }

    $f['slFil']    = SM_WlSession::langName2Code($f['slFil']);  //Accept language names as well as codes
    $f['levelMin'] = SM_csSess::levelVis2Num($f['levelMin'],'min');
    $f['levelMax'] = SM_csSess::levelVis2Num($f['levelMax'],'max');

    $whereClauses['BASE'] = 'clilstore.sl=lang.id AND clilstore.owner=users.user';
    if ($f['idFil']<>'')      { $whereClauses['id']         = 'clilstore.id=?';     }
    if ($f['viewsMin']<>'')   { $whereClauses['viewsMin']   = 'views>=?';           }
    if ($f['viewsMax']<>'')   { $whereClauses['viewsMax']   = 'views<=?';           }
    if ($f['clicksMin']<>'')  { $whereClauses['clicksMin']  = 'clicks>=?';          }
    if ($f['clicksMax']<>'')  { $whereClauses['clicksMax']  = 'clicks<=?';          }
    if ($f['createdMin']<>'') { $whereClauses['createdMin'] = 'created>=?';         }
    if ($f['createdMax']<>'') { $whereClauses['createdMax'] = 'created<=?';         }
    if ($f['changedMin']<>'') { $whereClauses['changedMin'] = 'changed>=?';         }
    if ($f['changedMax']<>'') { $whereClauses['changedMax'] = 'changed<=?';         }
    if ($f['licenceFil']<>'') { $whereClauses['licence']    = 'licence LIKE ?';     }
    if ($f['ownerFil']<>'')   { $whereClauses['owner']      = 'owner LIKE ?';       }
    if ($f['slFil']<>'')      { $whereClauses['sl']         = 'sl=?';               }
    if ($f['levelMin']!=='')  { $whereClauses['levelMin']   = 'level>=?';           }
    if ($f['levelMax']!=='')  { $whereClauses['levelMax']   = 'level<=?';           }
    if ($f['wordsMin']<>'')   { $whereClauses['wordsMin']   = 'words>=?';           }
    if ($f['wordsMax']<>'')   { $whereClauses['wordsMax']   = 'words<=?';           }
    if ($f['medtypeFil']<>'') { $whereClauses['medtype']    = 'clilstore.medtype=?';}
    if ($f['medlenMin']<>'')  { $whereClauses['medlenMin']  = 'medlen>=?';          }
    if ($f['medlenMax']<>'')  { $whereClauses['medlenMax']  = 'medlen<=?';          }
    if ($f['buttonsMin']<>'') { $whereClauses['buttonsMin'] = 'buttons>=?';         }
    if ($f['buttonsMax']<>'') { $whereClauses['buttonsMax'] = 'buttons<=?';         }
    if ($f['filesMin']<>'')   { $whereClauses['filesMin']   = 'files>=?';           }
    if ($f['filesMax']<>'')   { $whereClauses['filesMax']   = 'files<=?';           }
    if ($f['titleFil']<>'')   { $whereClauses['title']      = 'title LIKE ?';       }
    if ($f['textFil']<>'')    { $whereClauses['text']       = '(text LIKE ? OR summary LIKE ?)';  }
    if ($incTest==0)          { $whereClauses['test']       = ( empty($user)
                                                              ? 'test=0'
                                                              : "(test=0 OR owner='$user')" ); }

    $whereClause = implode(' AND ',$whereClauses);

    wildChars($f['licenceFil'],$licenceVis,'=');
    wildChars($f['ownerFil'],  $ownerVis,  '=');
    wildChars($f['titleFil'],  $titleVis,  'contains');
    wildChars($f['textFil'],   $textVis,   'contains');

    $csSess->setFilter($f);

    $idFil      = $f['idFil'];
    $viewsMin   = $f['viewsMin'];
    $viewsMax   = $f['viewsMax'];
    $clicksMin  = $f['clicksMin'];
    $clicksMax  = $f['clicksMax'];
    $createdMin = $f['createdMin'];
    $createdMax = $f['createdMax'];
    $changedMin = $f['changedMin'];
    $changedMax = $f['changedMax'];
    $licenceFil = $f['licenceFil'];
    $ownerFil   = $f['ownerFil'];
    $slFil      = $f['slFil'];
    $levelMin   = $f['levelMin'];
    $levelMax   = $f['levelMax'];
    $wordsMin   = $f['wordsMin'];
    $wordsMax   = $f['wordsMax'];
    $medtypeFil = $f['medtypeFil'];
    $medlenMin  = $f['medlenMin'];
    $medlenMax  = $f['medlenMax'];
    $buttonsMin = $f['buttonsMin'];
    $buttonsMax = $f['buttonsMax'];
    $filesMin   = $f['filesMin'];
    $filesMax   = $f['filesMax'];
    $titleFil   = $f['titleFil'];
    $textFil    = $f['textFil'];

    date_default_timezone_set('UTC');
    if ($createdMin<>'') { $createdMinQ = strtotime($f['createdMin'].'T00:00:00'); }
    if (!empty($createdMax)) { $createdMaxQ = strtotime($f['createdMax'].'T23:59:59'); }
    if (!empty($changedMin)) { $changedMinQ = strtotime($f['changedMin'].'T00:00:00'); }
    if (!empty($changedMax)) { $changedMaxQ = strtotime($f['changedMax'].'T23:59:59'); }
    $levelMin    = SM_csSess::levelVis2Num($levelMin,'min');
    $levelMax    = SM_csSess::levelVis2Num($levelMax,'max');
    $levelMinVis = SM_csSess::levelNum2Vis($levelMin,'min');
    $levelMaxVis = SM_csSess::levelNum2Vis($levelMax,'max');

    $idVal         =
    $viewsMinVal   =
    $viewsMaxVal   =
    $clicksMinVal  =
    $clicksMaxVal  =
    $createdMinVal =
    $createdMaxVal =
    $changedMinVal =
    $changedMaxVal =
    $licenceVal    =
    $ownerVal      =
    $slVal         =
    $levelMinVal   =
    $levelMaxVal   =
    $wordsMinVal   =
    $wordsMaxVal   =
    $medtypeVal    =
    $medlenMinVal  =
    $medlenMaxVal  =
    $buttonsMinVal =
    $buttonsMaxVal =
    $filesMinVal   =
    $filesMaxVal   =
    $titleVal      =
    $textVal       = '';

    if (!empty($idFil))      { $idVal         = "value=\"$idFil\"";      }
    if (!empty($viewsMin))   { $viewsMinVal   = "value=\"$viewsMin\"";   }
    if (!empty($viewsMax))   { $viewsMaxVal   = "value=\"$viewsMax\"";   }
    if (!empty($clicksMin))  { $clicksMinVal  = "value=\"$clicksMin\"";  }
    if (!empty($clicksMax))  { $clicksMaxVal  = "value=\"$clicksMax\"";  }
    if (!empty($createdMin)) { $createdMinVal = "value=\"$createdMin\""; }
    if (!empty($createdMax)) { $createdMaxVal = "value=\"$createdMax\""; }
    if (!empty($changedMin)) { $changedMinVal = "value=\"$changedMin\""; }
    if (!empty($changedMax)) { $changedMaxVal = "value=\"$changedMax\""; }
    if (!empty($licenceVis)) { $licenceVal    = "value=\"$licenceVis\""; }
    if (!empty($ownerVis))   { $ownerVal      = "value=\"$ownerVis\"";   }
    if (!($levelMin===''))   { $levelMinVal   = "value=\"$levelMinVis\"";}
    if (!($levelMax===''))   { $levelMaxVal   = "value=\"$levelMaxVis\"";}
    if (!empty($wordsMin))   { $wordsMinVal   = "value=\"$wordsMin\"";   }
    if (!empty($wordsMax))   { $wordsMaxVal   = "value=\"$wordsMax\"";   }
    if ($medtypeFil<>'')     { $medtypeVal    = "value=\"$medtypeFil\""; }
    if (!empty($medlenMin))  { $medlenMinVal  = "value=\"$medlenMin\"";  }
    if (!empty($medlenMax))  { $medlenMaxVal  = "value=\"$medlenMax\"";  }
    if (!empty($buttonsMin)) { $buttonsMinVal = "value=\"$buttonsMin\""; }
    if (!empty($buttonsMax)) { $buttonsMaxVal = "value=\"$buttonsMax\""; }
    if (!empty($filesMin))   { $filesMinVal   = "value=\"$filesMin\"";   }
    if (!empty($filesMax))   { $filesMaxVal   = "value=\"$filesMax\"";   }
    if (!empty($titleVis))   { $titleVal      = "value=\"$titleVis\"";   }
    if (!empty($textVis))    { $textVal       = "value=\"$textVis\"";    }


    $query = 'SELECT DISTINCT endonym,sl FROM clilstore,lang WHERE clilstore.sl=lang.id ORDER BY endonym';
    $stmt = $DbMultidict->prepare($query);
    $stmt->execute();
    $result = $stmt->fetchAll();
    $slOptions = array();
    $slOptions[] = '<option value="" style="background-color:white">'
                  .'</option><option value="ar">Arabic (ar)</option>'; //"Arabic" added in English as a special case
    foreach ($result as $lang) {
        $endonym = $lang['endonym'];
        $sl      = $lang['sl'];
        $selected = ( $sl==$slFil ? ' selected' : '');;
//      $codeInfo = ( $wideChecked ? " ($sl)" : '' );
        $codeInfo = " ($sl)";  //Decided to always include the language code
        $slOptions[] = "<option value=\"$sl\"$selected>$endonym$codeInfo</option>";
    }
    $slOptionsHtml = implode("\n",$slOptions);
    $slSelectColor = ( $slFil=='' ? 'white' : 'yellow' );

    if ($mode==0) {
        $levelButHtml = $csSess->levelButHtml();
        $tabletopChoices = <<<ENDtabletopChoices
<form id="selectForm" method="post" style="margin:1em 0">
Language <select name="sl" style="background-color:$slSelectColor" onchange="document.getElementById('selectForm').submit();">
$slOptionsHtml
</select>
<input type="submit" name="slSubmit" value="Choose">
</form>
$levelButHtml
ENDtabletopChoices;
    }

    $mode0commentoutStart  = ( $mode==0 ? '<!--' : '');
    $mode0commentoutFinish = ( $mode==0 ? '-->'  : '');

    $hoverColorOdd  = '#eef';
    $hoverColorEven = '#fff';
    if (empty($user)) { $highlightRow = 0; }
    else {
        $stmt = $DbMultidict->prepare('SELECT highlightRow FROM users WHERE user=:user');
        $stmt->execute(array(':user'=>$user));
        $highlightRow = $stmt->fetchObject()->highlightRow;
    }
    if ($highlightRow==1 || ($highlightRow==0 && $mode==3)) {
        $hoverColorOdd  = '#fe6';
        $hoverColorEven = '#fe6';
    }

    echo <<<EOD1
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Clilstore - Teaching units for content and language integrated learning</title>
    <link rel="StyleSheet" href="/css/smo.css" type="text/css">
    <link rel="StyleSheet" href="style.css">
    <link rel="icon" type="image/png" href="/favicons/clilstore.png">
    <style type="text/css">
        table#priomh { border:1px solid #888; border-collapse:collapse; white-space:nowrap; color:#111; }
        table#priomh tr td { padding:0 3px; vertical-align:top; }

        table#priomh tr.row1 td { background-color:#888; color:white; border-bottom:2px solid #888; }
        table#priomh tr.row2 td { background-color:#e2e2e2; padding-top:6px; }
        table#priomh tr.row1 td a { color:white; }
        table#priomh tr.row4 td {  background-color:#e2e2e2;padding-bottom:1px; border-bottom:1px solid #888; font-size:120%; }
        table#priomh tr.row4 td a:visited { color:#61abac; }

        table#priomh         td.id      { text-align:right; display:$idDisplay; }
        table#priomh         td.views   { text-align:right; display:$viewsDisplay; }
        table#priomh         td.clicks  { text-align:right; display:$clicksDisplay; }
        table#priomh         td.created { display:$createdDisplay; }
        table#priomh         td.changed { display:$changedDisplay; }
        table#priomh         td.licence { display:$licenceDisplay; }
        table#priomh         td.owner   { display:$ownerDisplay; }
        table#priomh         td.sl      { display:$slDisplay; }
        table#priomh         td.level   { text-align:center; display:$levelDisplay }
        table#priomh         td.words   { text-align:right; display:$wordsDisplay; }
        table#priomh         td.medtype { text-align:center; display:$medtypeDisplay; }
        table#priomh         td.medlen  { text-align:right; display:$medlenDisplay; }
        table#priomh         td.buttons { text-align:right; display:$buttonsDisplay; }
        table#priomh         td.files   { text-align:right; display:$filesDisplay; }
        table#priomh         td.delete  { display:$deleteDisplay; }
        table#priomh         td.edit    { display:$editDisplay; }
        table#priomh         td.nowl    { display:$nowlDisplay; }
        table#priomh         td.title   { display:$titleDisplay; }

        table#priomh tr.data td.id      { font-size:75%; padding-top:4px; text-align:right; display:$idDisplay }
        table#priomh tr.data td.views   { font-size:75%; padding-top:4px; text-align:right; display:$viewsDisplay; }
        table#priomh tr.data td.clicks  { font-size:75%; padding-top:4px; text-align:right; display:$clicksDisplay; }
        table#priomh tr.data td.created { font-size:75%; padding-top:4px; color:grey; display:$createdDisplay; }
        table#priomh tr.data td.changed { font-size:75%; padding-top:4px; color:grey; display:$changedDisplay; }
        table#priomh tr.data td.licence { font-size:75%; padding-top:5px; display:licenceDisplay; }
        table#priomh tr.data td.owner   { display:ownerDisplay; }
        table#priomh tr.data td.sl      { display:slDisplay; }
        table#priomh tr.data td.level   { text-align:center; display:levelDisplay; }
        table#priomh tr.data td.words   { font-size:75%; padding-top:4px; text-align:right; display:$wordsDisplay; }
        table#priomh tr.data td.medtype { display:$medtypeDisplay; }
        table#priomh tr.data td.medlen  { font-size:75%; padding-top:4px; text-align:right; display:$medlenDisplay; }
        table#priomh tr.data td.buttons { display:$buttonsDisplay; }
        table#priomh tr.data td.files   { display:$filesDisplay; }
        table#priomh tr.data td.delete  { display:$deleteDisplay; }
        table#priomh tr.data td.edit    { display:$editDisplay; }
        table#priomh tr.data td.nowl    { display:$nowlDisplay; }
        table#priomh tr.data td.title   { white-space:normal; padding-left:12px; text-indent:-10px; display:titleDisplay; }

        table#priomh tr.data:nth-child(odd)  { background-color:#eef; }
        table#priomh tr.data:nth-child(even) { background-color:#fff; }
        table#priomh tr.data:nth-child(odd):hover  { background-color:$hoverColorOdd; }
        table#priomh tr.data:nth-child(even):hover { background-color:$hoverColorEven; }

        div.find { float:right; margin:4px 0 0 0; background-color2:#fb0; padding:2px 22px 2px 1px; }
        div.find input { font-size:112%; background-color:#888; color:white; font-weight:bold; padding:3px 10px; border:0; border-radius:8px; }
        div.find input:hover { background-color:blue; }

        img.favicon { width:16px; height:16px; border:0; margin:2px; }
        a.button { display:block; float:left; margin:1px 7px; background-color:#55a8eb; color:white; font-weight:bold; padding:3px 10px; border:0; border-radius:8px; }
        a.button:hover { background-color:blue; }
a.mybutton { background-color:#55a8eb; color:white; padding:1px 8px; border-radius:8px; white-space:nowrap; }
a.mybutton:hover { background-color:blue; }
        a.levelbutton { margin:1px 7px; background-color:#55a8eb; color:white; font-weight:bold; padding:2px 8px; border:1px solid white; border-radius:8px; }
        a.levelbutton.selected { border-color:#55a8eb; background-color:yellow; color:#55a8eb; }
        a.levelbutton.grey { background-color:#ccc; }
        a.levelbutton.live:hover { background-color:blue; }

        select.con { border:0; text-align:center; }
        input[type=text][value] { background-color:yellow; }
        input[type=date][value] { background-color:yellow; }
        .italic { font-style:italic; }
        .arabicfont { font-family:"Times Roman","Times New Roman"; font-size:130%; }
        p.noUnits { color:red; background-color:yellow; }
        span.fann { color:grey; font-size:65%; }
    </style>
    <script type="text/javascript">
<!--
        function clearFields () {
            var el,elType;
            form = document.getElementById('filterForm');
            for(i=0; i<form.elements.length; i++) {
                el = form.elements[i];
                if (el.name=='incTest') continue;
                if (el.name=='wide')    continue;
                elType = el.type.toLowerCase();
                if (elType=='text' || elType=='date') {
                    el.value = '';
                    delete el.value; //All that is needed for Opera; the rest is for other browsers
                } else if (elType=='checkbox') {
                    el.checked = '';
                } else if (elType=='select-one') {
                    el.selectedIndex = 0;
                }
            }
            form.submit();
        }

        function submitFForm () {
            document.getElementById('filterForm').submit();
        }
//-->
    </script>
</head>
<body onload="history.pushState('','',location.pathname);">

<ul class="smo-navlist">
<li><a href="/" title="Multidict, Wordlink, and Clilstore">$servername</a></li>
</ul>
<div class="smo-body-indent">
<!--<span style="font-size:50%;color:red;background-color:yellow">News: Service will be down on 20 February 2016 during communications upgrade</span>-->

<h1 style="float:left;margin:10px 12px 0 0"><img src="/icons-smo/clilstore-blue45.png" alt="Clilstore" style="width:184px;height:45px"></h1>
<p style="margin:22px 0 0 0;font-size:90%;float:left">Teaching units<br>for Content and Language Integrated Learning</p>
<a href="help.html" class="button">Help</a>
<a href="about.html" class="button">About</a>
$photo

<div style="width:100%;min-height:1px;clear:both">

<form id="modeForm" method="get" style="float:left;padding:10px 0">
<select name="mode" style="background-color:white" onchange="document.getElementById('modeForm').submit();">
<option $mode0selected value="0">Student page</option>
<option $mode1selected value="1">Student page - more options</option>
<option $mode2selected value="2">Author page</option>
<option $mode3selected value="3">Author page - more options</option>
</select>
</form>

$addColHtml

$userHtml

$tabletopChoices

<form id="filterForm" method="post" onreset="clearFields();">
<input type="hidden" name="filterForm" value="1">

<datalist id="levelList">
<option value="A1">
<option value="A2">
<option value="B1">
<option value="B2">
<option value="C1">
<option value="C2">
</datalist>

<datalist id="licenceList">
<option value="BY-SA">
<option value="BY">
<option value="BY-ND">
<option value="BY-NC-SA">
<option value="BY-NC">
<option value="BY-NC-ND">
</datalist>

<datalist id="ownerList">
$ownerListHtml
</datalist>

$checkboxesHtml
<table><tr style="vertical-align:top">
<td style="min-width:690px">
<table id="priomh">
<tr class="row1">
 <td class="id"><a href="./?sortCol=id" title="Click to sort">id</a></td>
 <td class="views"><a href="./?sortCol=views" title="Click to sort">Views</a></td>
 <td class="clicks"><a href="./?sortCol=clicks" title="Click to sort">Clicks</a></td>
 <td class="created"><a href="./?sortCol=created" title="Click to sort">Created</a></td>
 <td class="changed"><a href="./?sortCol=changed" title="Click to sort">Changed</a></td>
 <td class="licence"><a href="./?sortCol=licence" title="Click to sort">Licence</a></td>
 <td class="owner"><a href="./?sortCol=owner" title="Click to sort">Owner</a></td>
 <td class="sl"><a href="./?sortCol=sl" title="Click to sort (by language code)">Language</a></td>
 <td class="level"><a href="./?sortCol=level" title="CEFR level - Click to sort">Level</a></td>
 <td class="words"><a href="./?sortCol=words" title="Number of words in text - Click to sort">Words</a></td>
 <td class="medtype"><a href="./?sortCol=medtype" title="Click to sort">Media</a></td>
 <td class="medlen"><a href="./?sortCol=medlen" title="Media length - Click to sort">Time</a></td>
 <td class="buttons"><a href="./?sortCol=buttons" title="Buttons - Click to sort">Buttons</a></td>
 <td class="files"><a href="./?sortCol=files" title="Files - Click to sort">Files</a></td>
 <td class="delete">&nbsp;</td>
 <td class="edit">&nbsp;</td>
 <td class="nowl">&nbsp;</td>
 <td class="title"><a href="./?sortCol=title" title="Click to sort">Title</a></td>
 <td>Text or Summary</td>
</tr>
<tr class="row2">
<td class="id"><input name="id" type="text" $idVal pattern="[0-9]{1,5}" tabindex=10 autofocus style="width:2.5em" onchange="submitFForm()"></td>
<td class="views"><input name="viewsMin" type="text" pattern="[0-9]{1,}" $viewsMinVal placeholder="min." title="minimum number of views" tabindex=14 style="width:3.5em;text-align:right" onchange="submitFForm()"></td>
<td class="clicks"><input name="clicksMin" type="text" pattern="[0-9]{1,}" $clicksMinVal placeholder="min." title="minimum number of clicks" tabindex=16 style="width:3.5em;text-align:right" onchange="submitFForm()"></td>
<td class="created"><input name="createdMin" type="date" $createdMinVal tabindex=20 title="start date" onchange="submitFForm()"></td>
<td class="changed"><input name="changedMin" type="date" $changedMinVal tabindex=30 title="start date" onchange="submitFForm()"></td>
<td class="licence"><input name="licence" type="text" $licenceVal tabindex=40 list="licenceList" style="width:4.7em" onchange="submitFForm()"></td>
<td class="owner"><input name="owner" type="text" $ownerVal tabindex=44 list="ownerList" style="width:10em" onchange="submitFForm()"></td>
<td class="sl">$mode0commentoutStart<select name="sl" style="background-color:$slSelectColor" tabindex=50 onchange="submitFForm()">
$slOptionsHtml
</select>$mode0commentoutFinish</td>
<td class="level">$mode0commentoutStart<input name="levelMin" type="text" $levelMinVal placeholder="min." list="levelList" title="minimum CEFR level" tabindex=60 style="width:2.8em;text-align:center" onchange="submitFForm()">$mode0commentoutFinish</td>
<td class="words"><input name="wordsMin" type="text" pattern="[0-9]{1,}" $wordsMinVal placeholder="min." title="minimum number of words in text" tabindex=62 style="width:3.5em;text-align:right" onchange="submitFForm()"></td>
<td class="medtype">$mode0commentoutStart<input name="medtype" type="text" $medtypeVal pattern="[0-2]" placeholder="0,1,2" title="0=none; 1=sound; 2=video" tabindex=64 style="width:2.1em" onchange="submitFForm()">$mode0commentoutFinish</td>
<td class="medlen"><input name="medlenMin" type="text" pattern="[0-9]{1,}" $medlenMinVal placeholder="min." title="minimum media length in seconds" tabindex=66 style="width:3.3em;text-align:right" onchange="submitFForm()"></td>
<td class="buttons"><input name="buttonsMin" type="text" pattern="[0-9]{1,}" $buttonsMinVal placeholder="min." title="minimum number of link buttons" tabindex=68 style="width:3.3em;text-align:right" onchange="submitFForm()"></td>
<td class="files"><input name="filesMin" type="text" pattern="[0-9]{1,}" $filesMinVal placeholder="min." title="minimum number of attached files" tabindex=70 style="width:3.3em;text-align:right" onchange="submitFForm()"></td>
<td class="delete"></td>
<td class="edit"></td>
<td class="nowl"></td>
<td class="title"><input name="title" type="text" $titleVal placeholder="contains" title="Part of the title" tabindex=72 style="width:17em" onchange="submitFForm()"></td>
<td class="title"><input name="text" type="text" $textVal placeholder="contains" title="Part of the text or summary" tabindex=74 style="min-width:10em;width:95%" onchange="submitFForm()"></td>
</tr>
<tr class="row3" style="background-color:#e2e2e2">
<td class="id"></td>
<td class="views"><input name="viewsMax" type="text" pattern="[0-9]{1,}" $viewsMaxVal placeholder="max." tabindex="15" style="width:3.5em;text-align:right" title="maximum number of views" onchange="submitFForm()"></td>
<td class="clicks"><input name="clicksMax" type="text" pattern="[0-9]{1,}" $clicksMaxVal placeholder="max." tabindex="16" style="width:3.5em;text-align:right" title="maximum number of clicks" onchange="submitFForm()"></td>
<td class="created"><input name="createdMax" type="date" $createdMaxVal tabindex=21 title="end date" onchange="submitFForm()"></td>
<td class="changed"><input name="changedMax" type="date" $changedMaxVal tabindex=31 title="end date" onchange="submitFForm()"></td>
<td class="licence"></td>
<td class="owner"></td>
<td class="sl"></td>
<td class="level">$mode0commentoutStart<input name="levelMax" type="text" $levelMaxVal placeholder="max." list="levelList" tabindex="61" title="maximum CEFR level" style="width:2.8em;text-align:center" onchange="submitFForm()">$mode0commentoutFinish</td>
<td class="words"><input name="wordsMax" type="text" pattern="[0-9]{1,}" $wordsMaxVal placeholder="max." tabindex="63" style="width:3.5em;text-align:right" title="maximum number of words in text" onchange="submitFForm()"></td>
<td class="medtype"></td>
<td class="medlen"><input name="medlenMax" type="text" pattern="[0-9]{1,}" $medlenMaxVal placeholder="max." tabindex="67" style="width:3.3em;text-align:right" title="maximum media length in seconds" onchange="submitFForm()"></td>
<td class="buttons"><input name="buttonsMax" type="text" pattern="[0-9]{1,}" $buttonsMaxVal placeholder="max." tabindex="69" style="width:3.3em;text-align:right" title="maximum number of link buttons" onchange="submitFForm()"></td>
<td class="files"><input name="filesMax" type="text" pattern="[0-9]{1,}" $filesMaxVal placeholder="max." tabindex="71" style="width:3.3em;text-align:right" title="maximum number of attached files" onchange="submitFForm()"></td>
<td class="delete"></td>
<td class="edit"></td>
<td class="nowl"></td>
<td class="title" colspan=2>
 <div class="find">
     <input type="submit" name="filter" value="Find" tabindex=80>&nbsp;&nbsp;
     <input type="reset" value="Clear filter" title="Clear all filtering" tabindex=90>
 </div>
</td>
</tr>
<tr class="row4">$symbolRowHtml</tr>

EOD1;


    $orderClause = $csSess->orderClause();
    $query = 'SELECT clilstore.id,owner,fullname,sl,endonym,level,words,medtype,medlen,buttons,files,title,text,summary,created,changed,licence,test,views,clicks'
            .' FROM clilstore,users,lang'
            ." WHERE $whereClause ORDER BY $orderClause";
    $stmt = $DbMultidict->prepare($query);
    $i = 1;
    if (!empty($whereClauses['id']))         { $stmt->bindParam($i++,$idFil);       }
    if (!empty($whereClauses['viewsMin']))   { $stmt->bindParam($i++,$viewsMin);    }
    if (!empty($whereClauses['viewsMax']))   { $stmt->bindParam($i++,$viewsMax);    }
    if (!empty($whereClauses['clicksMin']))  { $stmt->bindParam($i++,$clicksMin);   }
    if (!empty($whereClauses['clicksMax']))  { $stmt->bindParam($i++,$clicksMax);   }
    if (!empty($whereClauses['createdMin'])) { $stmt->bindParam($i++,$createdMinQ); }
    if (!empty($whereClauses['createdMax'])) { $stmt->bindParam($i++,$createdMaxQ); }
    if (!empty($whereClauses['changedMin'])) { $stmt->bindParam($i++,$changedMinQ); }
    if (!empty($whereClauses['changedMax'])) { $stmt->bindParam($i++,$changedMaxQ); }
    if (!empty($whereClauses['licence']))    { $stmt->bindParam($i++,$licenceFil);  }
    if (!empty($whereClauses['owner']))      { $stmt->bindParam($i++,$ownerFil);    }
    if (!empty($whereClauses['sl']))         { $stmt->bindParam($i++,$slFil);       }
    if (!empty($whereClauses['levelMin']))   { $stmt->bindParam($i++,$levelMin);    }
    if (!empty($whereClauses['levelMax']))   { $stmt->bindParam($i++,$levelMax);    }
    if (!empty($whereClauses['wordsMin']))   { $stmt->bindParam($i++,$wordsMin);    }
    if (!empty($whereClauses['wordsMax']))   { $stmt->bindParam($i++,$wordsMax);    }
    if (!empty($whereClauses['medtype']))    { $stmt->bindParam($i++,$medtypeFil);  }
    if (!empty($whereClauses['medlenMin']))  { $stmt->bindParam($i++,$medlenMin);   }
    if (!empty($whereClauses['medlenMax']))  { $stmt->bindParam($i++,$medlenMax);   }
    if (!empty($whereClauses['buttonsMin'])) { $stmt->bindParam($i++,$buttonsMin);  }
    if (!empty($whereClauses['buttonsMax'])) { $stmt->bindParam($i++,$buttonsMax);  }
    if (!empty($whereClauses['filesMin']))   { $stmt->bindParam($i++,$filesMin);    }
    if (!empty($whereClauses['filesMax']))   { $stmt->bindParam($i++,$filesMax);    }
    if (!empty($whereClauses['title']))      { $stmt->bindParam($i++,$titleFil);    }
    if (!empty($whereClauses['text']))       { $stmt->bindParam($i++,$textFil);
                                               $stmt->bindParam($i++,$textFil);     }
    $stmt->execute();
   //Initialise statistics
    $nunits = 0;
    $cnt['level'] = $cnt['medlen'] = 0;
    $tot['views'] = $tot['clicks'] = $tot['created'] = $tot['created2'] = $tot['changed'] = $tot['level'] = $tot['words'] = $tot['medlen'] = $tot['buttons'] = $tot['files'] = 0;
    $totalsRow = $avgRow = '';
   //
    while ($page = $stmt->fetch(PDO::FETCH_OBJ)) {
        $nunits++;
        $id      = $page->id;
        $owner   = $page->owner;
        $fullname= $page->fullname;
        $sl      = $page->sl;
        $endonym = $page->endonym;
        $level   = $page->level;
        $words   = $page->words;
        $medtype = $page->medtype;
        $medlen  = $page->medlen;
        $buttons = $page->buttons;
        $files   = $page->files;
        $title   = $page->title;
        $text    = $page->text;
        $summary = htmlspecialchars($page->summary);
        $created = $page->created;
        $changed = $page->changed;
        $licence = $page->licence;
        $test    = $page->test;
        $views   = $page->views;
        $clicks  = $page->clicks;
       //Increment statistics
        $tot['views']   += $views;
        $tot['clicks']  += $clicks;
        $tot['created'] += $created;
        $tot['created2']+= max($created,1395144000); //Adjusted for click count time, which only started on 2014-03-18
        $tot['changed'] += $changed;
        $tot['level']   += $level;
        $tot['words']   += $words;
        $tot['medlen']  += $medlen;
        $tot['buttons'] += $buttons;
        $tot['files']   += $files;
        $cnt['level']   += ($level>-1);
        $cnt['medlen']  += ($medlen>0);
       //
        $createdObj = new DateTime("@$created");
        $createdDate     = date_format($createdObj, 'Y-m-d');
        $createdDateTime = date_format($createdObj, 'Y-m-d H:i:s');
        $changedObj = new DateTime("@$changed");
        $changedDate     = date_format($changedObj, 'Y-m-d');
        $changedDateTime = date_format($changedObj, 'Y-m-d H:i:s');
        if ($changed==$created) { $changedDate = $changedDateTime = ''; }
        $ownerHtml = "<a href=\"userinfo.php?user=$owner\" title=\"$fullname\">$owner</a>";
        $editHtml = $deleteHtml = '';
        $cefrHtml = SM_csSess::cefrHtml($level);
        $testHtml  = ( empty($test) ? '' : '<img src="/icons-smo/undConst.gif" alt="" style="padding-left:16px"> ' );
        $titleClass = ( empty($test) ? 'title' : 'title italic' );
        if ($sl=='ar') { $titleClass .= ' arabicfont'; }
        $medlenHtml = ( ( empty($medlen) && ($medtype==0 || $owner<>$user  ) )
                      ? ''
                      : SM_csSess::secs2minsecs($medlen)
                      );
        if      ($medtype==1) { $medtypeHtml = "<img src=\"audio.png\" alt=\"snd\" title=\"$medlenHtml\">"; }
         elseif ($medtype==2) { $medtypeHtml = "<img src=\"video.png\" alt=\"vid\" title=\"$medlenHtml\">"; }
         else                 { $medtypeHtml = ''; }
        $buttonsHtml = ( empty($buttons) ? '' : $buttons );
        $filesHtml   = ( empty($files)   ? '' : $files   );
        if ($user==$owner || $user=='admin')  {
            $deleteHtml = "<a href=\"delete.php?id=$id\">"
              . '<img src="/icons-smo/curAs.png" alt="Delete" title ="Delete this unit" class="favicon"/></a>';
            $editHtml = "<a href=\"edit.php?id=$id\">"
              . '<img src="/icons-smo/peann.png" alt="Edit" title ="Edit this unit" class="favicon"/></a>';
        }
        echo '<tr class="data">'
           . "<td class=\"id\"><a href=\"/cs/$id\" title=\"$views views\">$id</a></td>"
           . "<td class=\"views\">$views</td>"
           . "<td class=\"clicks\">$clicks</td>"
           . "<td class=\"created\" title=\"$createdDateTime UT\">$createdDate</td>"
           . "<td class=\"changed\" title=\"$changedDateTime UT\">$changedDate</td>"
           . "<td class=\"licence\">$licence</td>"
           . "<td class=\"owner\">$ownerHtml</td>"
           . "<td class=\"sl\" title=\"language code: $sl\">$endonym</td>"
           . "<td class=\"level\">$cefrHtml</td>"
           . "<td class=\"words\">$words</td>"
           . "<td class=\"medtype\">$medtypeHtml</td>"
           . "<td class=\"medlen\">$medlenHtml</td>"
           . "<td class=\"buttons\">$buttonsHtml</td>"
           . "<td class=\"files\">$filesHtml</td>"
           . "<td class=\"delete\">$deleteHtml</td>"
           . "<td class=\"edit\">$editHtml</td>"
           . "<td class=\"nowl\"><a href=\"page.php?id=$id\">"
           .    '<img src="/favicons/no_wordlink.png" alt="no_wordlink" title="The plain page, not wordlinked" class="favicon"/></a></td>'
           . "<td class=\"$titleClass\" colspan=\"2\">$testHtml<a href=\"/cs/$id\" title=\"$summary\">$title</a></td>"
           . "</tr>\n";
    }
    $stmt = null;
    $DbMultidict = null;
    if ($nunits==0)     { $noUnitsMessage = "<p style=\"color:red;background-color:yellow\">No units match this filter.  You need to revise or Clear the filter.</p>\n"; }
     elseif ($nunits<2) { $noUnitsMessage = ''; }
     else { //Calculate and display statistics
         $noUnitsMessage  = "<p style=\"margin-top:0;color:grey;font-size:70%\">$nunits units found</p>";
         if ($mode>1) {
             $avgLevel  = ( $cnt['level']==0  ? '' : sprintf('%.1f',$tot['level']/$cnt['level']) );
             $avgLevelHtml = SM_csSess::cefrHtml($avgLevel);
             $avgMedlen = ( $cnt['medlen']==0 ? '' : SM_csSess::secs2minsecs(round($tot['medlen']/$cnt['medlen'])) );
             $avgCreated = round($tot['created']/$nunits);
             $avgCreatedObj = new DateTime("@$avgCreated");
             $avgCreatedDate     = date_format($avgCreatedObj, 'Y-m-d');
             $avgCreatedDateTime = date_format($avgCreatedObj, 'Y-m-d H:i:s');
             $avgChanged = round($tot['changed']/$nunits);
             $avgChangedObj = new DateTime("@$avgChanged");
             $avgChangedDate     = date_format($avgChangedObj, 'Y-m-d');
             $avgChangedDateTime = date_format($avgChangedObj, 'Y-m-d H:i:s');
             $timeNow = time();
             $totViewTime  = $nunits*$timeNow - $tot['created'];
             $totClickTime = $nunits*$timeNow - $tot['created2'];
             $viewRate  = $tot['views']/$totViewTime;
             $clickRate = $tot['clicks']/$totClickTime;
             function rateMessage($rate) { return sprintf('%.4g/day, %.4g/month, %.4g/year', $rate*86400, $rate*2630000, $rate*31557600); }
             $viewRateMessage  = rateMessage($viewRate);
             $clickRateMessage = rateMessage($clickRate);
             $totViewRateMessage  = rateMessage($viewRate *$nunits);
             $totClickRateMessage = rateMessage($clickRate*$nunits);
             $totalsRow = '<tr style="font-size:80%;color:grey;background-color:#ddd">'
                        . '<td class="id">Totals:</td>'
                        . '<td class="views"  title="'.$totViewRateMessage .'">' . $tot['views']  . '</td>'
                        . '<td class="clicks" title="'.$totClickRateMessage.'">' . $tot['clicks'] . '</td>'
                        . '<td class="created"></td>'
                        . '<td class="changed"></td>'
                        . '<td class="licence"></td>'
                        . '<td class="owner"></td>'
                        . '<td class="sl"></td>'
                        . '<td class="level"></td>'
                        . '<td class="words">'  . $tot['words']  . '</td>'
                        . '<td class="medtype"></td>'
                        . '<td class="medlen">' . SM_csSess::secs2minsecs($tot['medlen']) . '</td>'
                        . '<td class="buttons">'. $tot['buttons']. '</td>'
                        . '<td class="files">'  . $tot['files']  . '</td>' 
                        . '<td colspan="5"></td>'
                        . '</tr>';
             $avgRow    = '<tr style="font-size:80%;color:grey;background-color:#ddd;border-top:solid 1px #888">'
                        . '<td class="id">Avg.:</td>'
                        . '<td class="views"  title="'.$viewRateMessage .'">'   . sprintf('%.1f',$tot['views']/$nunits)    . '</td>'
                        . '<td class="clicks" title="'.$clickRateMessage.'">'   . sprintf('%.1f',$tot['clicks']/$nunits)   . '</td>'
                        . "<td class='created' title='$avgCreatedDateTime UT'>$avgCreatedDate</td>"
                        . "<td class='changed' title='$avgChangedDateTime UT'>$avgChangedDate</td>"
                        . '<td class="licence"></td>'
                        . '<td class="owner"></td>'
                        . '<td class="sl"></td>'
                        . '<td class="level">'  . $avgLevelHtml  . '</td>'
                        . '<td class="words">'  . sprintf('%.1f',$tot['words']/$nunits)  . '</td>'
                        . '<td class="medtype"></td>'
                        . '<td class="medlen">' . $avgMedlen . '</td>'
                        . '<td class="buttons">'. sprintf('%.1f',$tot['buttons']/$nunits) . '</td>'
                        . '<td class="files">'  . sprintf('%.1f',$tot['files']/$nunits)  . '</td>'
                        . '<td colspan="5"></td>'
                        . '</tr>';
         }
    }
    echo <<<EOD2
$avgRow
$totalsRow
</table>
$noUnitsMessage
</td>
<td>$studentphoto</td>
</tr></table>
</form>

<div style="min-height:65px;max-width:1000px;border:2px solid #47d;margin:8em 0 2em 0;border-radius:4px;color:#47d;font-size:95%">
<div style="float:left;margin-right:1.5em">
<a href="http://eacea.ec.europa.eu/llp/index_en.php"><img src="/EUlogo.png" alt="" style="margin:3px"></a>
</div>
<div style="min-height:59px">
<p style="margin:1px 0 0 0">This project has been funded with support from the European Commission.</p>
<p style="margin:8px 0 0 0;color:#47d;font-size:75%">Disclaimer:
This publication reflects the views only of the author, and the Commission cannot be held responsible for any use which may be made of the information contained therein.</p>
</div>
</div>
</div>

</div>
<ul class="smo-navlist" style="clear:both">
<li><a href="/" title="Multidict, Wordlink, and Clilstore">$servername</a></li>
</ul>

</body>
</html>
EOD2;

  } catch (Exception $e) { echo $e; }

?>