<?php

require_once 'config.php'; // This will have to exist in the parent frame before this iFrame is loaded

// Get the account data returned by the API
$accounts = json_decode($accountData);
$accounts = $accounts->response->items;

$accountToUse = ( isset($_COOKIE['account']) ? $_COOKIE['account'] : $accounts[0]->id ); // Default to use the first account
foreach ( $accounts as $account ) // Get the URL of the account we're currently looking at
{
	if ( $accountToUse == $account->id )
	{
		$accountUrl = $account->stagebloc_url;
		break;
	}
}

// Find all of the available themes we have
$themes = array_values(preg_grep('/^([^.])/', scandir($themePath))); // Ignore hidden files
$themeToUse = ( isset($_COOKIE['theme']) ? $_COOKIE['theme'] : $themes[0] ); // Default to use the first theme

// Get the theme and determine if there are any includes in it
$html = file_get_contents($themePath . $themeToUse . '/theme.sbt');
if ( $themeViewsPath !== null )
{
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
					$html = str_replace($option_variable_string, file_get_contents($themePath . $themeToUse . '/' . $themeViewsPath . $options_matches[2][$key]), $html);
				}
			}
		}
	}
}

// Pass out theme data to the API to be rendered
$postData = array(
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
		if ( $cssPath !== null ) // If this var isn't null, we'll check another folder for the CSS files
		{
			// Add a <link> tag for each CSS file in the folder we linked to
			$cssFiles = scandir($themePath . $themeToUse . '/' . $cssPath);
			foreach ( $cssFiles as $cssFile )
			{
				if ( strpos($cssFile, '.css') !== false )
				{
					// This method will dump the CSS into the page itself and probably isn't very useful
					//$renderedTheme = str_replace('</head>', '<style>' . file_get_contents($themePath . $themeToUse . '/' . $cssPath . $cssFile) . '</style></head>', $renderedTheme);
					
					$renderedTheme = str_replace('</head>', '<link rel="stylesheet" type="text/css" href="' . $themePath . $themeToUse . '/' . $cssPath . $cssFile . '"></head>', $renderedTheme);
				}
			}
		}
		else
		{
			$renderedTheme = str_replace('</head>', '<link rel="stylesheet" type="text/css" href="' . $themePath . $themeToUse . '/style.css"></head>', $renderedTheme);
		}
	}

	// If we didn't pass the JS, we'll append it to the rendered theme
	if ( ! isset($postData['js']) )
	{
		if ( $jsPath !== null ) // If this var isn't null, we'll check another folder for the JS files
		{
			// Add a <script> tag for each JS file in the folder we linked to
			$jsFiles = scandir($themePath . $themeToUse . '/' . $jsPath);
			foreach ( $jsFiles as $jsFile )
			{
				if ( strpos($jsFile, '.js') !== false )
				{
					$renderedTheme = str_replace('</head>', '<script src="' . $themePath . $themeToUse . '/' . $jsPath . $jsFile . '"></script></head>', $renderedTheme);
				}
			}
		}
		else
		{
			$renderedTheme = str_replace('</head>', '<script src="' . $themePath . $themeToUse . '/javascript.js"></script></head>', $renderedTheme);
		}
	}

	// Change all the anchor tags so links render through the API instead of elsewhere
	$renderedTheme = preg_replace('#href="((http:\/\/stagebloc\..+)?\/' . $accountUrl . '\/)#', 'href="?url=', $renderedTheme);

	// Output the final result
	echo $renderedTheme;
}
catch ( Services_StageBloc_Invalid_Http_Response_Code_Exception $e )
{
	die($e->getMessage());
}

?>

<script>
	$(function()
	{
		if ( window.self !== window.top ) // Only push state if we're in the iFrame
		{
			$('a').click(function(e) {
				var data = {
					url: '//' + location.hostname + $(this).attr('href')
				};

				if ( history.pushState )
				{
					// Push the state of this to the parent so that refreshing the page loads the same page
					parent.history.pushState(data, document.title, data.url);
				}
			});
		}
	});
</script>