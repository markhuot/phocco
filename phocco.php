#!/usr/bin/php

<?php

/**
 * __Phocco__ is a PHP port of Docco, the quick-and-dirty, hundred-line-long,
 * literate-programming-style documentation generator.
 * 
 * Phocco reads source files and produces annotated source documentation
 * in HTML format. Comments are formatted with Markdown and presented
 * alongside syntax highlighted code so as to give an annotation effect. This
 * page is the result of running Phocco against its own source file.
 * 
 * Most of this was written while waiting for Ruby, Python, Node, etc… to
 * build (so I could use Docco!). Docco’s gorgeous HTML and CSS are taken
 * verbatim. The main difference is that Phocco is written in PHP and _has no
 * dependancies_!
 * 
 * That's right, it uses remotely hosted Javascript files to parse the markdown
 * on the left and the syntax highlighting on the right.
 * 
 * The [source for Phocco][1] is available on GitHub, and released under the
 * MIT license.
 * 
 * Install Phocco... by downloading it. It should run on just about any version
 * of PHP. That means it'll work on a vanilla MAMP install or a custom PHP
 * install.
 * 
 * Once installed, the `phocco` command can be used to generate documentation
 * for a set of source files:
 * 
 *     php phocco lib/*.php
 * 
 * The HTML files are written to the current working directory.
 * 
 * [1]:http://markhuot.github.com/phocco/
 */

/**
 * Loop over an array of files generating documentation for each file.
 * Unfortunately users can enter any file they want here, some relative to the
 * current working directory, others absolute. We don't know. So, we'll turn
 * every path into a full path then down to a relative path. That way we're
 * certain everything below is only dealing with relative paths.
 */
function generate_documentation_for_files($files) {
	foreach ($files as $key => $file) {
		$files[$key] = relative_path(getcwd(), realpath($file));
	}

	// $a = '/'.(dirname($files[2])=='.'?'':dirname($files[2]));
	// $b = '/'.(dirname($files[0])=='.'?'':dirname($files[1]));
	// var_dump($a);
	// var_dump($b);
	// var_dump(relative_path($a, $b));
	// die;

	foreach ($files as $file) {
		generate_documentation_for_file($file, $files);
	}
}

/**
 * Generates documentation for a single file. This function also accepts a
 * list of files to generate the page switcher.
 */
function generate_documentation_for_file($file, $files=array()) {
	echo "Generating documentation: {$file}\n";
	$source = file_get_contents(realpath($file));
	$sections = parse($source);
	render($file, $sections, $files);
}

/**
 * ## Parsing
 *
 * Parse the source code into sections. A section is simply an array with the
 * first index being the comment and the second index being the code. This
 * should work with most languages since we're not looking for anything
 * specific to PHP.
 */
function parse($source) {
	$sections = $doc = $code = array();

	// If the first line is a shebang or opening PHP tag the strip it out.
	// Yea, not everyone will agree with this but it's not totally relevant to
	// the docs and it prevents comments from appearing at the top of the docs.
	$source = preg_replace('/^\#\!.*[\r\n]+/', '', $source);
	$source = preg_replace('/^\s*<\?php\s*/s', '', $source);

	// Do the split
	$lines = preg_split('/\n/', $source);

	// Store state as we loop through the lines. We need to know if the last
	// line was a single (`_s`) or multiline (`_m`) comment so we know what to do with the
	// current line.
	$in_comment_s = FALSE;
	$in_comment_m = FALSE;

	// Loop over each line.
	// Of note here is that each condition inside this causes the loop to
	// continue to the next line. If none of the conditions are met we
	// assume the content is code and dump it into the code half.
	foreach ($lines as $line) {

		// Are we ending a multiline comment? If so, add the line to the
		// documentation half and close out the comment variable.
		if ($in_comment_m && preg_match('/^\s*\*\/\s*$/', $line)) {
			$doc[] = preg_replace('/^\s*\*\/\s*$/', '', $line);
			$in_comment_m = FALSE;
			$in_comment_s = FALSE;
			continue;
		}

		// Are we in a multiline comment? If so, add the line to the
		// documentation half.
		if ($in_comment_m) {
			$doc[] = preg_replace('/^\s*\*\s?/', '', $line);
			$in_comment_s = FALSE;
			continue;
		}

		// Are we starting a multiline comment? If so, start a new section by
		// appending the current buffer of `$code` and `$doc` to sections. Then
		// reset everything and start a new section.
		if (preg_match('/^\s*\/\*\*/', $line)) {
			if ($doc || $code) {
				$sections[] = array(implode("\n", $doc), implode("\n", $code));
				$doc = $code = array();
			}
			$doc[] = preg_replace('/^\s*\/\*\*/', '', $line);
			$in_comment_m = TRUE;
			$in_comment_s = FALSE;
			continue;
		}
		
		// Are we in a single line comment? If we are then we'll add this line
		// to the doc half. If we're following code then start a new section.
		// If we're following a single line comment then just add it as a
		// continuation of the last comment.
		if (preg_match('/^\s*\/\//', $line)) {
			if (!$in_comment_s && ($doc || $code)) {
				$sections[] = array(implode("\n", $doc), implode("\n", $code));
				$doc = $code = array();
			}
			$doc[] = preg_replace('/^\s*\/\//', '', $line);
			$in_comment_s = TRUE;
			continue;
		}
		
		// If we got here then we're not in any comments and we should just
		// add the line to the code half.
		$code[] = $line;
		$in_comment_s = FALSE;
	}

	// Add the final buffer into the sections array.
	$sections[] = array(implode("\n", $doc), implode("\n", $code));

	// Give back.
	return $sections;
}

/**
 * ## Rendering
 * 
 * Parses the sections out into HTML.
 */
function render($file, $sections, $files) {
	$cwd = rtrim(getcwd(), '/').'/';
	$docs = $cwd.'docs/';
	$rendered_file = $docs.$file.'.html';

	$html = view_base(array(
		'file' => $file,
		'docs' => $docs,
		'display_name' => basename($file),
		'extension' => extension($file),
		'files' => $files,
		'sections' => $sections
	));

	rmkdir(dirname($rendered_file));
	file_put_contents($rendered_file, $html);
}

/**
 * ## Views
 * 
 * Because it's a single file we split our views up into discrete functions to
 * approximate the same effect.
 *
 * First up is the base HTML view that everything else is built off.
 */
function view_base($vars) {
extract($vars);
ob_start(); ?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="content-type" content="text/html;charset=utf-8">
		<title><?php echo $display_name; ?></title>
		<link rel="stylesheet" href="http://markhuot.github.com/phocco/resources/phocco.css">
		<link href="http://google-code-prettify.googlecode.com/svn/trunk/src/prettify.css" type="text/css" rel="stylesheet" />
		<link href="http://alexgorbatchev.com/pub/sh/current/styles/shThemeDefault.css" rel="stylesheet" type="text/css" />
		<style type="text/css">
			.syntaxhighlighter,
			.syntaxhighlighter .line.alt1,
			.syntaxhighlighter .line.alt2 {
				background:none !important;
			}

			td.code td.code {
				padding:0;border:none;
			}
		</style>
	</head>
	<body>
		<div id="container">
		<div id="background"></div>
			<?php echo view_jump($vars); ?>
			<?php echo view_sections($vars); ?>
		</div>
		<?php echo view_javascript($vars); ?>
	</body>
</html>
<?php
$str = ob_get_contents();
ob_end_clean();
return $str;
}

/**
 * The `jump_to` list allows you to browse to other files. There's a little
 * trickery here to create relative links from every page. That way you can
 * view Phocco files by double clicking them in the finder.
 */
function view_jump($vars) {
extract($vars);
ob_start(); ?>
<?php if (count($files) > 1): ?>
	<div id="jump_to">
		<a id="jump_handle" href="#">Jump&nbsp;To&hellip;</a>
		<div id="jump_wrapper">
			<div id="jump_page">
				<?php foreach ($files as $sibling): ?>
					<a class="source" href="[
						<?php
						$a = '/'.dirname($file)=='.'?'':dirname($file);
						$b = '/'.dirname($sibling)=='.'?'':dirname($sibling);
						echo relative_path($a, $b);
						?>].html">
						<?php echo $sibling; ?>
					</a>
				<?php endforeach ; ?>
			</div>
		</div>
	</div>
<?php endif; ?>
<?php $str = ob_get_contents();
ob_end_clean();
return $str;
}

/**
 * The `sections` table is the meat of the page. It generates the split view.
 * Of note, the doc and code have to be flush left so that markdown parses
 * correctly and the syntax highlighter works appropriately.
 */
function view_sections($vars) {
extract($vars);
ob_start(); ?>
<table cellspacing=0 cellpadding=0>
	<thead>
		<tr>
			<th class=docs><h1><?php echo $display_name; ?></h1></th>
			<th class=code></th>
		</tr>
	</thead>
	<tbody>

		<?php foreach ($sections as $count => $section): ?>
			<tr id="section-'<?php echo $count ?>'">
				<td class="docs">
					<div class="pilwrap">
						<a class="pilcrow" href="#section-<?php echo $count ?>">&#182;
						</a>
					</div>
<div class="doc">
<?php echo $section[0]; ?>
</div>
				</td>
				<td class="code">
					<div class="highlight">
<pre class="brush: <?php echo $extension; ?>">
<?php echo htmlentities($section[1]); ?>
</pre>
					</div>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php $str = ob_get_contents();
ob_end_clean();
return $str;
}

/**
 * All the messy javascript. This is where markdown parses and where syntax
 * highlighting is kicked off.
 */
function view_javascript($vars) {
extract($vars);
ob_start(); ?>
<script src="http://code.jquery.com/jquery-1.7.1.min.js"></script>
<script src="http://markhuot.github.com/phocco/resources/showdown.js"></script>
<script>
	converter = new Showdown.converter();
	$(".doc").each(function() {
		$(this).html(converter.makeHtml($(this).text()));
	});

	$("#jump_handle").click(function(e){
		$("#jump_wrapper").toggle();
		e.preventDefault();
	});
</script>
<script src="https://raw.github.com/alexgorbatchev/SyntaxHighlighter/master/scripts/XRegExp.js"></script>
<script src="https://raw.github.com/alexgorbatchev/SyntaxHighlighter/master/scripts/shCore.js" type="text/javascript"></script>
<script src="http://alexgorbatchev.com/pub/sh/current/scripts/shAutoloader.js" type="text/javascript"></script>
<script type="text/javascript">
	function path()
	{
	  var args = arguments,
	      result = []
	      ;
	       
	  for(var i = 0; i < args.length; i++)
	      result.push(args[i].replace("@", "http://alexgorbatchev.com/pub/sh/current/scripts/"));
	       
	  return result
	};

	SyntaxHighlighter.autoloader.apply(null, path(
	  "applescript            @shBrushAppleScript.js",
	  "actionscript3 as3      @shBrushAS3.js",
	  "bash shell             @shBrushBash.js",
	  "coldfusion cf          @shBrushColdFusion.js",
	  "cpp c                  @shBrushCpp.js",
	  "c# c-sharp csharp      @shBrushCSharp.js",
	  "css                    @shBrushCss.js",
	  "delphi pascal          @shBrushDelphi.js",
	  "diff patch pas         @shBrushDiff.js",
	  "erl erlang             @shBrushErlang.js",
	  "groovy                 @shBrushGroovy.js",
	  "java                   @shBrushJava.js",
	  "jfx javafx             @shBrushJavaFX.js",
	  "js jscript javascript  @shBrushJScript.js",
	  "perl pl                @shBrushPerl.js",
	  "php                    @shBrushPhp.js",
	  "text plain             @shBrushPlain.js",
	  "py python              @shBrushPython.js",
	  "ruby rails ror rb      @shBrushRuby.js",
	  "sass scss              @shBrushSass.js",
	  "scala                  @shBrushScala.js",
	  "sql                    @shBrushSql.js",
	  "vb vbnet               @shBrushVb.js",
	  "xml xhtml xslt html    @shBrushXml.js"
	));
	SyntaxHighlighter.defaults["light"] = true;
	SyntaxHighlighter.defaults["unindent"] = false;
	SyntaxHighlighter.all();
</script>
<?php $str = ob_get_contents();
ob_end_clean();
return $str;
}

/**
 * ## Helpers
 *
 * Just a simple file to recurively create directories. Takes a single path
 * and loops through each segment creating them as we go.
 */
function rmkdir($path) {
	$dirs = preg_split('/\//', ltrim($path, '/'));
	for ($i=1; $len=count($dirs),$i<=$len; $i++) {
		if (!is_dir($dir = '/'.implode('/', array_slice($dirs, 0, $i)))) {
			mkdir($dir);
		}
	}
}

/**
 * Get a path relative to the current working directory
 */
function cwd_path($path) {
	$cwd = rtrim(getcwd(), '/').'/';
	return preg_replace('/^'.preg_quote($cwd, '/').'/', '', $path);
}

function relative_path($from, $to=FALSE, $ps=DIRECTORY_SEPARATOR) {
	if (!$to) {
		$to = rtrim(getcwd(), '/').'/';
	}

	if ($from == '.') { $from = ''; }
	if ($to == '.') { $to = ''; }

	$from = explode($ps, rtrim($from, $ps));
	$to = explode($ps, rtrim($to, $ps));

	while(count($from) && count($to) && ($from[0] == $to[0]))
	{
		array_shift($from);
		array_shift($to);
	}

	return str_pad("", count($from) * 3, '..'.$ps).implode($ps, $to);
}

/**
 * Return the file extension of the passed file/path.
 */
function extension($file) {
	return preg_replace('/^.*\.(.*)$/', '$1', $file);
}

/**
 * ## Do It!
 */
generate_documentation_for_files(array_slice($argv, 1));