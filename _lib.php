<?php


include_once('_config.php');

// Die if we can't find config
if (CONFIG_FILE != 'PRESENT') {
  print "<h2>Drush Make Generator could not find _config.php</h2><ul><li>Copy _config.example.php to _config.php</li><li>Alter the settings to work with your web host and database</li></ul>";
  exit;
}




/**
 * Fetches all contrib projects as a raw DB object
 */
function fetchContrib($limit=NULL) {
  global $version;
  if ($limit && is_numeric($limit)) { $limitClause = ' LIMIT '.$limit; }
  else { $limitClause = ''; }
  $projectsSQL = sprintf("SELECT * FROM  `projects` WHERE `type` IN ('module','theme') AND `version` = '%s' AND `url` = '' AND `status` = 1 %s; ",$version,$limitClause);
  $projects = mysql_query($projectsSQL);
  
  return $projects;
}


/**
 * Fetches module projects as a raw DB object
 */
function fetchModules() {
  global $version;
  $projectsSQL = sprintf("SELECT * FROM  `projects` WHERE `type` = 'module' AND `version` = '%s' AND `url` = '' AND `status` = 1; ",$version);
  $projects = mysql_query($projectsSQL);
  
  return $projects;
}


/**
 * Fetches theme projects as a raw DB object
 */
function fetchThemes() {
  global $version;
  $projectsSQL = sprintf("SELECT * FROM  `projects` WHERE `type` = 'theme' AND `version` = '%s' AND `url` = '' AND `status` = 1; ",$version);
  $projects = mysql_query($projectsSQL);
  
  return $projects;
}





/**
 * Generates the form for generating makefiles (for generating Drupal sites :)
 */
function formMakefile($v){
  $output = '';
  
  $output .= formCores($v);
  $output .= '<fieldset id="fs-contrib">
    <legend><p>Modules</p></legend>';
  $output .= formModules($v);
  $output .= '</fieldset>
  <fieldset id="fs-themes">
    <legend><p>Themes</p></legend>';
  $output .= formThemes($v);
  $output .= '</fieldset>
  <fieldset id="fs-libs">
    <legend><p>Libraries</p></legend>';
  $output .= formLibs($v);
  $output .= '</fieldset>
  <fieldset id="fs-opts">
    <legend><p>Options</p></legend>';
  $output .= formOpts($v);
  $output .= '</fieldset>';
  
  return $output;

}



/**
 * Outputs the major Drupal version fieldset
 */
function formVersion($v){
  $v = ($v) ? $v : $version;  
  $output = '';
  
  if ($v == 6){$checked6 = 'checked="checked"'; $checked7 = '';$checked8 = ''; }
  if ($v == 7){$checked6 = ''; $checked7 = 'checked="checked"';$checked8 = ''; }
  if ($v == 8){$checked6 = ''; $checked7 = '';$checked8 = 'checked="checked"'; }
  
  $output .= '<fieldset id="fs-version">
    <legend><p>Drupal Version <span class="small">Since different modules and themes are available for each major version<br /> of Drupal, the form will be <strong>reset</strong> if you change this setting.</span></p></legend>
    <label for="o-version6"><input id="o-version6" type="radio" name="makefile[version]" value="6" '.$checked6.' /> <span class="title">Drupal 6</span></label>
    <label for="o-version7"><input id="o-version7" type="radio" name="makefile[version]" value="7" '.$checked7.'/> <span class="title">Drupal 7</span></label>
    <label for="o-version8"><input id="o-version8" type="radio" name="makefile[version]" value="8" '.$checked7.'/> <span class="title">Drupal 8</span></label>
  </fieldset>';
  
  return $output;
  
}

/**
 * Outputs a fieldset with all the options for Drupal core
 */
function formCores($v){
  $v = ($v) ? $v : $version;
  $output = '';
  
  $coresSQL = sprintf("SELECT  *,`unique` AS coreName FROM  `projects` WHERE `type` = 'core' AND `version` = '%s' AND `status` = 1 ORDER BY `unique` ASC; ",$v);
  $cores = mysql_query($coresSQL);
  
	$output .= '<fieldset class="fs-core">
			<legend><p>Drupal core or distribution</p></legend>';

  while($c = mysql_fetch_assoc($cores)):
    if ($c['unique'] == 'drupal') {
      $selected = ' checked';
    } else {
      $selected = '';
    }
  	$output .= '
  				<label for="'. $c['unique'] .'-stable">
  					<input id="'. $c['unique'] .'-stable" type="radio" name="makefile[core]" value="'. $c['unique'] .'"'. $selected .' /> <span class="title">'.$c['title'].'</span>
  				</label>'."\r\n";
  endwhile;
  
  $output .= "\t".'</fieldset>'."\r\n";

  return $output;
}


/**
 * Outputs fieldsets for all the contrib modules, grouped by package name (same groups as /admin/build/modules)
 */
function formModules($v){
  $v = ($v) ? $v : $version;
  $output = '';
  
  $groupsSQL = sprintf("SELECT DISTINCT package as groupName FROM `projects` WHERE `type` = 'module' AND `package` <> '' AND `version` = '%s' AND `status` = 1 ORDER BY package ASC; ",$v);
  $groups = mysql_query($groupsSQL);
  
  while ($group = mysql_fetch_assoc($groups)) {
  
    $sql = sprintf(
      "SELECT p.`id`,`unique`,`title`,GROUP_CONCAT(v.release,'__',v.type ORDER BY v.type DESC SEPARATOR '%s') as `releases` ".
      "FROM `projects` AS p ".
      "LEFT JOIN `versions` AS v ON p.id = v.pid ".
      "WHERE p.package = '%s' AND p.version = '%s' AND `status` = 1 AND p.type = 'module' ".
      "GROUP BY p.unique ".
      "ORDER BY p.unique ASC; ",
      SQL_SEPARATOR,
      $group['groupName'],$v
      );
    $projects = mysql_query($sql);

    $groupSafe = str_replace(' ','_',strtolower($group['groupName']));

		$output .= "\r\n\t\t\t\t".'<h4>'.$group['groupName'].'</h4>';

    while($p = mysql_fetch_assoc($projects)):
      if (isset($p['releases'])){
        $releases = explode(SQL_SEPARATOR,$p['releases']);
      } else {
        $releases = FALSE;
      }
    	
    	$output .= '
    				<label for="'. $p['unique'] .'-stable">
    					<input id="'. $p['unique'] .'-stable" type="checkbox" /> <span class="title">'.$p['title'].'</span>
    					<select id="'. $p['unique'].'" name="makefile[modules]['. $p['unique'] .']" disabled="disabled">';
              if ($releases){
      				  foreach($releases as $r){
      				    $parts = explode('__',$r);
      				    if ($parts[1] == STABLE) {
        				    $output .= '<option value="'.$parts[0].'">Recommended</option>';
      				    } else {
        				    $output .=' <option value="'. $parts[0] .'">Use '. $parts[0] .'</option>';
        				  }
      				  }
      				}
    				  $output .= '</select>';
    	$output .= "\r\n\t\t\t\t".'</label>'."\r\n\t\t\t\t";
      
    endwhile;
    
  }

  $output .= '<div class="modules downloads">';
  $output .= formDownload('modules',array('url'=>'#url','unique'=>'module'));
  $output .= '</div>';
  $output .= formDownload('add');

  return $output;
}


/**
 * Outputs fieldsets for all the contrib themes alphabetically
 */
function formThemes($v){
  $v = ($v) ? $v : $version;
  $output = '';
  
  $sql = sprintf(
    "SELECT p.`id`,`unique`,`title`,GROUP_CONCAT(v.release,'__',v.type ORDER BY v.type DESC SEPARATOR '%s') as `releases` ".
    "FROM `projects` AS p ".
    "LEFT JOIN `versions` AS v ON p.id = v.pid ".
    "WHERE p.version = '%s' AND `status` = 1 AND p.type = 'theme' ".
    "GROUP BY p.unique ".
    "ORDER BY p.unique ASC; ",
    SQL_SEPARATOR,
    $v
    );
  $projects = mysql_query($sql);

  while($p = mysql_fetch_assoc($projects)):
    $releases = explode(SQL_SEPARATOR,$p['releases']);
    
  	$output .= '
  				<label for="'. $p['unique'] .'-stable">
  					<input id="'. $p['unique'] .'-stable" type="checkbox" name="makefile[themes]['. $p['unique'] .']" value="stable" /> <span class="title">'.$p['title'].'</span>
  					<select id="'. $p['unique'].'" name="makefile[themes]['. $p['unique'] .']" disabled="disabled">';
              if ($releases){
      				  foreach($releases as $r){
      				    $parts = explode('__',$r);
      				    if ($parts[1] == STABLE) {
        				    $output .= '<option value="'.$parts[0].'">Recommended</option>';
      				    } else {
        				    $output .=' <option value="'. $parts[0] .'">Use '. $parts[0] .'</option>';
        				  }
      				  }
      				}
  				  $output .= '</select>';
  	$output .= "\r\n\t\t\t\t".'</label>'."\r\n\t\t\t\t";
    
  endwhile;
  
  $output .= '<div class="themes downloads">';
  $output .= formDownload('themes',array('url'=>'#url','unique'=>'theme'));
  $output .= '</div>';
  $output .= formDownload('add');

  return $output;
}


/**
 * Outputs all the external libraries;
 * Includes a widget to add more
 */
function formLibs(){
  $output = '';
  
  $sql = sprintf(
    "SELECT p.`id`,`unique`,`title`,GROUP_CONCAT(CONCAT(v.release,'~~~',v.url) ORDER BY v.release DESC SEPARATOR '%s') as `releases` ".
    "FROM `projects` AS p ".
    "LEFT JOIN `versions` AS v ON p.id = v.pid ".
    "WHERE `status` = 1 AND p.type = 'lib' ".
    "GROUP BY p.unique ".
    "ORDER BY p.unique ASC; ",
    SQL_SEPARATOR
    );
  $projects = mysql_query($sql);

  while($p = mysql_fetch_assoc($projects)):
    $releases = explode(SQL_SEPARATOR,$p['releases']);
    
    $latest = explode('~~~',$releases[0]);
  	$output .= '
  				<label for="'. $p['unique'] .'-stable">
  					<input id="'. $p['unique'] .'-stable" type="checkbox" name="makefile[libs]['. $p['unique'] .']" value="'.$latest[1].'" /> <span class="title">'.$p['title'].'</span>
  					<select id="'. $p['unique'].'" name="makefile[libs]['. $p['unique'] .']" disabled="disabled">
  					 <option value="'.$latest[1].'">Latest ('.$latest[0].')</option>';
  				  
  				  array_shift($releases);
  				  
  				  foreach($releases as $r){
  				    $info = explode('~~~',$r);
  				    $output .=' <option value="'. $info[1] .'">Use '. $info[0] .'</option>';
  				  }
  				  $output .= '</select>';
  	$output .= "\r\n\t\t\t\t".'</label>'."\r\n\t\t\t\t";
    
  endwhile;
  
  $output .= '<div class="libraries downloads">';
  $output .= formDownload('libraries',array('url'=>'#url','unique'=>'library'));
  $output .= '</div>';
  $output .= formDownload('add');
    
  return $output;
}



/**
 * Outputs options for the makefile
 */
function formOpts($version){
  $output = '';
  $domain = $_SERVER['HTTP_HOST'];
  
  $output .= '<h4>Put modules in: </h4>
    <label for="o-contribdir">
      /sites/all/modules/
      <input id="o-contribdir" type="text" name="makefile[opts][contrib_dir]" placeholder="'. CONTRIB_DIR .'" />
    </label>';
/*
  $output .= '<h4>To ease setup: </h4>
    <label for="o-prep">
      include <a href="https://github.com/rupl/drush_make_generator/raw/master/prep.sh" target="_blank">prep.sh</a>&nbsp;
      <input id="o-prep" type="checkbox" name="makefile[opts][prep]" value="include" />
    </label>
  $output .= '<br><h4>Short URL:</h4>
    <label for="o-short">
      http://'.$domain.'/a/
      <input id="o-prep" type="text" name="makefile[opts][alias]" placeholder="alias" />
    </label>';
*/

  return $output;
  
}



/**
 * Outputs a single form element for a download. Either empty or populated.
 */
function formDownload($type='libraries',$download=array()){
  $output = '';
  
  if (empty($download['unique'])) {$download['unique'] = '[project]'; }
  if (empty($download['url'])) {$download['url'] = '#url'; }

  switch ($type) {
  
    case 'libraries':
      $output .= '<div class="download label">';
        $output .= '<a class="remove">remove</a>';
        $output .= '<select name="makefile[libs][|THIS|][type]" class="type"><option value="file">www</option><option value="git">git</option></select>';
        $output .= '<input type="text" class="unique" name="makefile[libs][|THIS|][unique]" placeholder="'.$download['unique'].'" /> ';
        $output .= '<input type="text" class="url" name="makefile[libs][|THIS|][url]" placeholder="'.$download['url'].'" />';
        $output .= '<input type="hidden" name="makefile[libs][|THIS|][maketype]" value="libraries" />';
      $output .= '</div>';
      break;

    case 'modules':
      $output .= '<div class="download label">';
        $output .= '<a class="remove">remove</a>';
        $output .= '<select name="makefile[modules][|THIS|][type]" class="type"><option value="drupal">drupal.org/project/</option><option value="file">www</option><option value="git">git</option></select>';
        $output .= '<input type="text" class="unique" name="makefile[modules][|THIS|][unique]" placeholder="'.$download['unique'].'" /> ';
        $output .= '<input type="text" class="url" name="makefile[modules][|THIS|][url]" placeholder="'.$download['url'].'" disabled="disabled" />';
        $output .= '<input type="hidden" name="makefile[modules][|THIS|][maketype]" value="module" />'; // module, not "modules"
      $output .= '</div>';
      break;
    
    case 'themes':
      $output .= '<div class="download label">';
        $output .= '<a class="remove">remove</a>';
        $output .= '<select name="makefile[themes][|THIS|][type]" class="type"><option value="drupal">drupal.org/project/</option><option value="file">www</option><option value="git">git</option></select>';
        $output .= '<input type="text" class="unique" name="makefile[themes][|THIS|][unique]" placeholder="'.$download['unique'].'" /> ';
        $output .= '<input type="text" class="url" name="makefile[themes][|THIS|][url]" placeholder="'.$download['url'].'" disabled="disabled" />';
        $output .= '<input type="hidden" name="makefile[themes][|THIS|][maketype]" value="theme" />'; // theme, not "themes"
      $output .= '</div>';
      break;
    
    case 'includes':
      $output .= '<div class="download label">';
        $output .= '<a class="remove">remove</a>';
        $output .= '<select name="makefile[includes][|THIS|][type]" class="type"><option value="drupal">drupal.org/project/</option><option value="file">www</option><option value="git">git</option></select>';
        $output .= '<input type="text" class="unique" name="makefile[includes][|THIS|][unique]" placeholder="'.$download['unique'].'" /> ';
        $output .= '<input type="text" class="url" name="makefile[includes][|THIS|][url]" placeholder="'.$download['url'].'" disabled="disabled" />';
        $output .= '<input type="hidden" name="makefile[includes][|THIS|][maketype]" value="includes" />';
      $output .= '</div>';
      break;
    
    case 'add':
      $output .= '<a class="another">Add Another</a>';
      break;
    
    default:
      break;
  }

  return $output;
}


/**
 * fetch makefile and output
 */
function generateMakefile($token,$opts=array()){
  $makefile = '';

  $clean = sanitize('token',$token);

  $sql = sprintf("SELECT * FROM `makefiles` WHERE token = '%s' LIMIT 1; ",$clean);
  $result = mysql_query($sql);
  
  if ($m = mysql_fetch_assoc($result)){
      $version  = $m['version'];
      $core     = unserialize($m['core']);
      $modules  = unserialize($m['modules']);
      $themes   = unserialize($m['themes']);
      $libs     = unserialize($m['libs']);
      $opts     = unserialize($m['opts']);
      $share    = TRUE;

      $makefile = makeMakefile($clean,$version,$core,$modules,$themes,$libs,$opts);

      return $makefile;
  } else {
    return FALSE;
  }

}



/**
 * makefile template
 */
function makeMakefile($token,$v,$core,$modules,$themes,$libs,$opts){
  $opts['version'] = $v;

  $makefile = '; ----------------
; Generated makefile from http://drushmake.me
; Permanent URL: '.fileURL($token,$opts).'
; ----------------
;
; This is a working makefile - try it! Any line starting with a `;` is a comment.
  
; Core version
; ------------
; Each makefile should begin by declaring the core version of Drupal that all
; projects should be compatible with.
  
core = '.$opts['version'].'.x
  
; API version
; ------------
; Every makefile needs to declare its Drush Make API version. This version of
; drush make uses API version `2`.
  
api = 2
  
; Core project
; ------------
; In order for your makefile to generate a full Drupal site, you must include
; a core project. This is usually Drupal core, but you can also specify
; alternative core projects like Pressflow. Note that makefiles included with
; install profiles *should not* include a core project.
  
'.makeCore($core,$opts).'
  
  
; Modules
; --------
'.makeModules($modules,$opts).'
  

; Themes
; --------
'.makeThemes($themes,$opts).'
  
  
; Libraries
; ---------
'.makeLibs($libs,$opts).'

'; // end of makefile
  
  return $makefile;

}


/**
 * Makes core request for makefile
 */
function makeCore($core='drupal',$opts) {
  $output = '';
  
  switch($core.$opts['version']):

    // 6.x

    case 'openatrium6':
    case 'openatrium':
      $output .= '; Use Open Atrium instead of Drupal core:'."\r\n";
      $output .= 'projects[openatrium][type] = "core"'."\r\n";
      $output .= 'projects[openatrium][download][type] = "get"'."\r\n";
      $output .= 'projects[openatrium][download][url] = "http://openatrium.com/sites/openatrium.com/files/atrium_releases/atrium-1-0-beta9.tgz"'."\r\n";
      break;

    case 'pressflow6':
    case 'pressflow':
      $output .= '; Use Pressflow instead of Drupal core:'."\r\n";
      $output .= 'projects[pressflow][type] = "core"'."\r\n";
      $output .= 'projects[pressflow][download][type] = "get"'."\r\n";
      $output .= 'projects[pressflow][download][url] = "http://files.pressflow.org/pressflow-6-current.tar.gz"'."\r\n";
      break;

    case 'drupal6':
      $output .= '; Drupal 6.x core:'."\r\n";
      $output .= 'projects[drupal][version] = 6'."\r\n";
      break;

    // 7.x

    case 'pressflow7':
      $output .= '; Use Pressflow instead of Drupal core:'."\r\n";
      $output .= 'projects[pressflow][type] = "core"'."\r\n";
      $output .= 'projects[pressflow][download][type] = "git"'."\r\n";
      $output .= 'projects[pressflow][download][url] = "git://github.com/pressflow/7.git"'."\r\n";
      break;

    case 'spark7':
      $output .= '; Spark for Drupal 7'"\r\n";
      $output .= 'projects[spark][download][type] = "git"'."\r\n";
      $output .= 'projects[spark][download][url] = "http://git.drupal.org/project/spark.git"'."\r\n";
      $output .= 'projects[spark][download][branch] = "7.x-1.x"'."\r\n";
      break;
    
    case 'drupal7':
      $output .= '; Drupal 7.x. Requires the `core` property to be set to 7.x.'."\r\n";
      $output .= 'projects[drupal][version] = 7'."\r\n";
      break;

      // 8.x

    case 'spark8':
      $output .= '; Spark for Drupal 8'"\r\n";
      $output .= 'projects[spark][download][type] = "git"'."\r\n";
      $output .= 'projects[spark][download][url] = "http://git.drupal.org/project/spark.git"'."\r\n";
      $output .= 'projects[spark][download][branch] = "8.x-1.x"'."\r\n";
      break;

    case 'drupal8':
      $output .= '; Drupal 8.x.'."\r\n";
      $output .= 'projects[drupal][version] = 8'."\r\n";
      break;

    default:
      $output .= '; No drupal core was selected'."\r\n";
      
  endswitch;

  return $output;

}


/**
 * Makes module requests for the makefile
 */
function makeModules($modules=array(),$opts=array()){
  global $version;
  $v = $version;  
  $subdir = ($opts['contrib_dir']) ? $opts['contrib_dir'] : '';
  $output = '';

  // loop through modules
  if ($modules){
    foreach($modules as $k => $v){
      $loop = '';

      // is this a custom download?
      if (strpos($k,'|') !== FALSE) {
        // yes, it is a download.
        $unique = str_replace('|','',$k);
        $loop .= makeDownload('projects',$k,$v,$opts);
        
        // include subdir if present
        if ($subdir && strpos($v['type'],'drupal') !== FALSE) {
          // erase previous line of output for official d.o downloads. for cleanliness
          $loop = '';
          $loop .= 'projects['.$unique.'][subdir] = '.$subdir."\r\n";
        }
        elseif ($subdir) {
          $loop .= 'projects['.$unique.'][subdir] = '.$subdir."\r\n";
        }
        
        $output .= $loop;
      }
      // no, this is a "standard" module already in the db
      else {
        if ($v == 'stable'){
          $loop .= 'projects[] = '.$k;
        }
        elseif ($v) {
          $loop .= 'projects['.$k.'][version] = '.$v;
        }
        else {
        }

        $loop .= "\r\n";
        $loop .= 'projects['.$k.'][type] = "module"';
        $loop .= "\r\n";
        
        // if a subdir is present, erase previous line and re-output with subdir. for cleanliness
        if ($subdir && $v == 'stable'){$loop = ''; }
        if ($subdir && $v) {$loop .= 'projects['.$k.'][subdir] = "'.$subdir.'"'."\r\n"; }
        
        $output .= $loop;        
      }
    }
  }
  
  return $output;
}


/**
 * Makes theme requests for the makefile
 */
function makeThemes($themes=array(),$opts=array()){
  global $version;
  $v = $version;  
  $output = '';

  // loop through themes
  if ($themes):
    foreach($themes as $k => $v){
      $loop = '';

      // unset the directory because the _downloadXXX functions are not yet smart enough to ignore contrib_dir for themes/libraries
      unset($opts['contrib_dir']);

      if (strpos($k,'|') !== FALSE) {
        $output .= makeDownload('projects',$k,$v,$opts);
      }
      else {
        if ($v == 'stable'){$loop .= 'projects[] = '.$k; }
        else {$loop .= 'projects['.$k.'][version] = '.$v; }
        
        $loop .= "\r\n";
        $loop .= 'projects['.$k.'][type] = "theme"';
        $loop .= "\r\n";
        
        $output .= $loop;
      }
    }
  endif;
  
  return $output;
}


/**
 * Makes library requests for the makefile
 */
function makeLibs($libs=array(),$opts=array()){
  $output = $loop = '';
    
  // loop through libraries
  if ($libs):
    foreach($libs as $k => $v){
      $loop = '';
      
      if (strpos($k,'|') !== FALSE) {
        $loop .= makeDownload('libraries',$k,$v,$opts);
        $output .= $loop;
      }
      else {        
        $loop .=
          'libraries['.$k.'][download][type] = "file"'."\r\n".
          'libraries['.$k.'][download][url] = "'.$v.'"'."\r\n";      
  
        $output .= $loop;
      }
    }
  endif;
  
  if (!$loop){
    $output = '; No libraries were included';
    //$output .= "; Adding a module such as jquery_update will never add the related library automatically.\r\n; https://github.com/rupl/drush_make_generator/issues/closed#issue/1 \r\n";
  }
  
  return $output;
}


/**
 * Delegates downloads
 */
function makeDownload($type,$unique,$data,$opts) {
  $output = '';
  $unique = str_replace('|','',$unique);
  
  switch ($data['type']) {
    case 'file':
      $output = _downloadFile($type,$unique,$data,$opts);
      break;
    
    case 'git':
      $output = _downloadGit($type,$unique,$data,$opts);
      break;

    case 'drupal':
      $output = _downloadDrupal($type,$unique,$data,$opts);
      break;

    // SCREW CVS VIVA LA GIT!!

    default:
      break;
  
  }
  return $output;
}



/**
 * Makes a single http request within the makefile, only called by makeDownload()
 */
function _downloadFile($type='',$unique,$data=array(),$opts=array()) {
  $output = '';
  $output .=
    $type.'['.$unique.'][type] = "'.$data['maketype'].'"'."\r\n".
    $type.'['.$unique.'][download][type] = "file"'."\r\n".
    $type.'['.$unique.'][download][url] = "'.$data['url'].'"'."\r\n";

  return $output;
}


/**
 * Makes a single git request within the makefile, only called by makeDownload()
 */
function _downloadGit($type='',$unique,$data=array(),$opts=array()) {
  $output = '';
  $output .=
    $type.'['.$unique.'][type] = "'.$data['maketype'].'"'."\r\n".
    $type.'['.$unique.'][download][type] = "git"'."\r\n".
    $type.'['.$unique.'][download][url] = "'.$data['url'].'"'."\r\n";

  return $output;
}


/**
 * Makes a single Drupal request within the makefile, only called by makeDownload()
 */
function _downloadDrupal($type='',$unique,$data=array(),$opts=array()) {
  $output = '';

  // if ($unique && $data['url']) {$output .= 'projects['.$unique.'] = '.$url; } // we're using $url for the module version. example: 6.x-2.0
  if ($unique) {$output .= 'projects[] = '.$unique; }
  else {$output .= '; ERROR: _downloadDrupal could not properly build a request for "'.$unique.'"'; }

  $output .= "\r\n";

  return $output;
}


/**
 * Makes a single CVS request for the makefile, only called by makeDownload()
 */
function _downloadCvs($type='',$unique,$data=array(),$opts=array()) {
  $output = '';
  $output .=
    'projects['.$unique.'][type] = '.$data['maketype']."\r\n".
    'projects['.$unique.'][download][type] = "cvs"'."\r\n".
    'projects['.$unique.'][download][url] = "'.$unique.'"'."\r\n";

  return $output;
}





/**
 * Sanitizes input
 */
function sanitize($type='token',$data){
  switch ($type) {
    case 'token':
      // only accept 12 chars made of a-f and 0-9
      $clean = ($data && preg_match('/^[a-f0-9]{12}/',$data)) ? $data : FALSE;
      break;
    
    case 'alias':
      // only accept 1-64 chars a-z, 0-9, hyphens, and underscores
      $data = strtolower($data);
      $string = preg_replace('/[^a-z0-9\-_]/','',$data);
      $clean = ($string && preg_match('/^[a-z0-9\-_]{1,64}$/',$string)) ? $string : FALSE;
      break;

    default:
      $clean = FALSE;
      break;
  }
  
  return $clean;
}


/**
 * Generate URL requests for a token.
 */
function fileURL($token='',$opts=array()){
  $domain = $_SERVER['HTTP_HOST'];

  // raw output mode
  $raw = '';
  if (isset($opts['raw']) && $opts['raw'] == 'raw') {
    $raw = TRUE;
  }

  // format URL
  if (!empty($opts['alias'])) {
    $raw = ($raw) ? '/raw' : '';
    $token = $opts['alias'];
    $url = 'http://'.$domain.'/a/'.$token.$raw;
  } else {
    $raw = ($raw) ? '&raw' : '';
    $url = 'http://'.$domain.'/file.php?token='.$token.$raw;
  }

  return $url;

}



