<?php

require_once 'config.php'; // This will have to exist in the parent frame before this iFrame is loaded
require_once 'stagebloc_theme.php';

function recursiveTemplateInclude($html, $path) {
	// Find all of the Includes inside of the theme
	preg_match_all('#\{(Includ[^:{}"]+)(\s[^{}]+\=[^{}]*)?\}#ims', $html, $includes);
	foreach ( $includes[0] as $key => $option_variable_string )
	{
		if ( preg_match_all('#([a-zA-Z]*)="([^"]*)"#', $option_variable_string, $options_matches) )
		{
			// For each include, find which file we should be putting in its place
			foreach ( $options_matches[1] as $key => $option )
			{
				if ( strtolower($option) === 'file' )
				{
					$included_template = recursiveTemplateInclude(file_get_contents($path . $options_matches[2][$key]), $path);
					$html = str_replace($option_variable_string, $included_template, $html);
				}
			}
		}
	}

	return $html;
}

// Get the account data returned by the API
$accounts = json_decode($accountData);
$accounts = $accounts->response->items;
$accountUrl = $customDomain = null;

$accountToUse = ( isset($_COOKIE['account']) ? $_COOKIE['account'] : $accounts[0]->id ); // Default to use the first account
foreach ( $accounts as $account ) // Get the URL of the account we're currently looking at
{
	if ( $accountToUse == $account->id )
	{
		$customDomain = $account->custom_domain;
		$accountUrl = $account->stagebloc_url;
		break;
	}
}

// Find all of the available themes we have
$themes = array_values(preg_grep('/^([^.])/', scandir($themePath))); // Ignore hidden files
$themeToUse = ( isset($_COOKIE['theme']) ? $_COOKIE['theme'] : $themes[0] ); // Default to use the first theme
$currentThemePath = $themePath . $themeToUse;

if( file_exists($currentThemePath . '/config.php') ) {
  $config = require($currentThemePath . '/config.php');

  if ( ! empty($config) ) {
    StageBlocTheme::loadConfig($config);
  }
}

// Get the theme and determine if there are any includes in it
$html = file_get_contents($currentThemePath . '/theme.sbt');
if ( $themeViewsPath !== null )
{
	$html = recursiveTemplateInclude($html, $currentThemePath . '/' . $themeViewsPath);
}

// Pass out theme data to the API to be rendered
$postData = array(
	'simulate_logged_out_user' => $simulateLoggedOutUser,
	'url' => ( isset($_GET['url']) ? $_GET['url'] : '' ), // The URL of the page to render
	'html' => $html

	// We don't need to pass the CSS and JS since we can just add it in to the parsed theme we receive from the API and save the bandwidth
	// Note: That being said, if your CSS has Option vars, you should pass it so that they are parsed by the engine. If your CSS is in seperate files, you'll need to concatenate those first
	//'css' => file_get_contents($themePath . $themeToUse . '/style.css'),
	//'js' => file_get_contents($themePath . $themeToUse . '/javascript.js'),
);

try
{
	$renderedTheme = $stagebloc->post('theme/render', $postData);

	// If we didn't pass the CSS, we'll append it to the rendered theme
	if ( ! isset($postData['css']) )
	{
    if ( StageBlocTheme::hasCustomCSS() )
    {
      $linkTags = '';
      foreach( StageBlocTheme::$cssFiles as $filePath ) {
        $linkTags .= sprintf(StageBlocTheme::$linkTagSyntax, $currentThemePath, $filePath);
      }

			$renderedTheme = str_replace('</head>', $linkTags . '</head>', $renderedTheme);
    }
    else if ( $cssPath !== null ) // If this var isn't null, we'll check another folder for the CSS files
		{
			// Add a <link> tag for each CSS file in the folder we linked to
			$cssFiles = scandir($currentThemePath . '/' . $cssPath);
			foreach ( $cssFiles as $cssFile )
			{
				if ( strpos($cssFile, '.css') !== false )
				{
					// This method will dump the CSS into the page itself and probably isn't very useful
					//$renderedTheme = str_replace('</head>', '<style>' . file_get_contents($themePath . $themeToUse . '/' . $cssPath . $cssFile) . '</style></head>', $renderedTheme);

					$renderedTheme = str_replace('</head>', '<link rel="stylesheet" type="text/css" href="' . $currentThemePath . '/' . $cssPath . $cssFile . '"></head>', $renderedTheme);
				}
			}
		}
		else
		{
			$renderedTheme = str_replace('</head>', '<link rel="stylesheet" type="text/css" href="' . $currentThemePath . '/style.css"></head>', $renderedTheme);
		}
	}

	// If we didn't pass the JS, we'll append it to the rendered theme
	if ( ! isset($postData['js']) )
	{
    if ( StageBlocTheme::hasCustomJS() )
    {
      $scriptTags = '';
      foreach( StageBlocTheme::$jsFiles as $filePath ) {
        $scriptTags .= sprintf(StageBlocTheme::$scriptTagSyntax, $currentThemePath, $filePath);
      }

			$renderedTheme = str_replace('</head>', $scriptTags . '</head>', $renderedTheme);
    }
    else if ( $jsPath !== null ) // If this var isn't null, we'll check another folder for the JS files
		{
			// Add a <script> tag for each JS file in the folder we linked to
			$jsFiles = scandir($currentThemePath . '/' . $jsPath);
			foreach ( $jsFiles as $jsFile )
			{
				if ( strpos($jsFile, '.js') !== false )
				{
					$renderedTheme = str_replace('</head>', '<script src="' . $currentThemePath . '/' . $jsPath . $jsFile . '"></script></head>', $renderedTheme);
				}
			}
		}
		else
		{
			$renderedTheme = str_replace('</head>', '<script src="' . $currentThemePath . '/javascript.js"></script></head>', $renderedTheme);
		}
	}

	// Change all the anchor tags so links render through the API instead of elsewhere
	if ( $customDomain )
	{
		// If it's a custom domain, the replacement is a little bit different
		$renderedTheme = preg_replace('#href="(http:\/\/(?:www\.)?' . $customDomain . '\/?)#', 'href="?url=', $renderedTheme);
	}
	$renderedTheme = preg_replace('#href="((http:\/\/stagebloc\..+)?\/' . $accountUrl . '\/)#', 'href="?url=', $renderedTheme);

	echo $renderedTheme; // Output the final result
}
catch ( Services_StageBloc_Invalid_Http_Response_Code_Exception $e )
{
	die($e->getMessage());
}

?>
<script>
if ( window.self !== window.top && history.pushState ) // Only push state if we're in the iframe
{
	[].forEach.call(document.getElementsByTagName('a'), function(el)
	{
		el.addEventListener('click', function()
		{
			var data = { url: '//' + location.hostname  + window.location.pathname.replace('theme_view.php', '') + el.getAttribute('href') };
			// Push the state of this to the parent so that refreshing the page loads the same page
			parent.history.pushState(data, document.title, data.url);
		});
	});
}
</script>
