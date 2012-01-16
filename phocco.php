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
 * Most of this was written while waiting for Ruby, Python, Node, etc… Docco
 * to build (so I could use Docco!). Docco’s gorgeous HTML and CSS are taken
 * verbatim. The main difference is that Phocco is written in PHP and _has no
 * dependancies_!
 * 
 * That's right, it uses remote hosted Javascript files to parse the markdown
 * on the left and the syntax highlighting on the right.
 * 
 * Install Phocco… by downloading it. It should run on just about any version
 * of PHP. That means it'll work on a vanilla MAMP install or a custom PHP
 * install.
 * 
 * Once installed, the `phocco` command can be used to generate documentation
 * for a set of source files:
 * 
 *     php phocco lib/*.rb
 * 
 * The HTML files are written to the current working directory.
 */

/**
* Loop over an array of files generating documentation for each file.
*/
function generate_documentation_for_files($files) {
	foreach ($files as $file) {
		generate_documentation_for_file(realpath($file), $files);
	}
}

/**
 * Generates documentation for a single file.
 * This function also accepts a list of files to generate the page switcher.
 */
function generate_documentation_for_file($file, $files=array()) {
	$source = file_get_contents($file);
	$sections = parse($source);
	render($file, $sections, $files);
}

function parse($source) {
	$sections = $doc = $code = array();
	$lines = preg_split('/\n+/', $source);

	if (preg_match('/^\#\!/', $lines[0])) {
		array_shift($lines);
	}

	if (preg_match('/^\s*<\?php\s*$/', $lines[0])) {
		array_shift($lines);
	}

	$in_comment = FALSE;
	foreach ($lines as $line) {
		if ($in_comment && preg_match('/\*\/\s*$/', $line)) {
			$in_comment = FALSE;
			$doc[] = preg_replace('/\*\/\s*$/', '', $line);
		}
		else if ($in_comment) {
			$doc[] = preg_replace('/\s*\*\s/', '', $line);
		}
		else if (preg_match('/^\s*\/\*\*/', $line)) {
			if ($doc || $code) {
				$sections[] = array(implode("\n", $doc), implode("\n", $code));
				$doc = $code = array();
			}
			$in_comment = TRUE;
			$doc[] = preg_replace('/^\s*\/\*\*/', '', $line);
		}
		else if (preg_match('/^\s*\/\//', $line)) {
			if ($doc || $code) {
				$sections[] = array(implode("\n", $doc), implode("\n", $code));
				$doc = $code = array();
			}
			$doc[] = preg_replace('/^\s*\/\//', '', $line);
		}
		else {
			$code[] = $line;
		}
	}
	$sections[] = array(implode("\n", $doc), implode("\n", $code));

	return $sections;
}

function render($file, $sections, $files) {
	$basename = basename($file);
	$extension = preg_replace('/^.*\.(.*)$/', '$1', $basename);

	$src = '<!DOCTYPE html><html><head><meta http-equiv="content-type" content="text/html;charset=utf-8"><title>'.$basename.'</title><link rel="stylesheet" href="docco.css"><script src="http://code.jquery.com/jquery-1.7.1.min.js"></script><script src="https://raw.github.com/coreyti/showdown/master/src/showdown.js"></script><link href="http://google-code-prettify.googlecode.com/svn/trunk/src/prettify.css" type="text/css" rel="stylesheet" /><!--script type="text/javascript" src="http://google-code-prettify.googlecode.com/svn/trunk/src/prettify.js"--></script><link href="http://alexgorbatchev.com/pub/sh/current/styles/shThemeDefault.css" rel="stylesheet" type="text/css" /><style type="text/css">.syntaxhighlighter,.syntaxhighlighter .line.alt1,.syntaxhighlighter .line.alt2{background:none !important;} td.code td.code {padding:0;border:none;}</style></head><body><div id="container"><div id="background"></div>';

	if (count($files) > 1) {
		$src.= '<div id="jump_to"><a id="jump_handle" href="#">Jump To &hellip;</a><div id="jump_wrapper"><div id="jump_page">';

		foreach ($files as $file) {
			$src.= '<a class="source" href="'.basename($file).'.html">'.basename(($file)).'</a>';
		}

		$src.= '</div></div></div>';
	}

	$src.= '<table cellspacing=0 cellpadding=0><thead><tr><th class=docs><h1>'.$basename.'</h1></th><th class=code></th></tr></thead><tbody>';

	foreach ($sections as $count => $section) {
		$src.= '<tr id="section-'.$count.'"><td class="docs"><div class="pilwrap"><a class="pilcrow" href="#section-'.$count.'">&#182;</a></div><div class="doc">';
		$src.= $section[0];
		$src.= '</div></td><td class="code"><div class="highlight"><pre class="brush: '.$extension.'">';
		$src.= htmlentities($section[1]);
		$src.= '</pre></div></td></tr>';
	}

	$src.= '</table></div><script>converter = new Showdown.converter();$(".doc").each(function() { $(this).html(converter.makeHtml($(this).text())); }); $("#jump_handle").click(function(e){ $("#jump_wrapper").toggle(); e.preventDefault(); }); </script><script src="https://raw.github.com/alexgorbatchev/SyntaxHighlighter/master/scripts/XRegExp.js"></script><script src="https://raw.github.com/alexgorbatchev/SyntaxHighlighter/master/scripts/shCore.js" type="text/javascript"></script><script src="http://alexgorbatchev.com/pub/sh/current/scripts/shAutoloader.js" type="text/javascript"></script><script type="text/javascript">
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
	</script></body>';

	$docs = rtrim(getcwd(), '/').'/docs/';
	if (!is_dir($docs)) {
		mkdir($docs);
	}

	file_put_contents($docs.$basename.'.html', $src);
}

generate_documentation_for_files(array_slice($argv, 1));