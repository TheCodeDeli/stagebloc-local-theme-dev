<?php

$loginRequired = false;

// Include the StageBloc library
require_once 'php-stagebloc-api/StageBloc.php';

// Check to see if they've configured their application
if ( file_exists('config.php') )
{
	require_once 'config.php';
	
	if ( strpos($accessToken, ' ') !== false || empty($accessToken) )
	{
		$loginRequired = true;
	}
}

if ( ! empty($_POST) ) // The user is attempting to login
{
	if ( isset($_POST['email']) && isset($_POST['password']) )
	{
		$params = array(
			'client_id' => $clientId,
			'client_secret' => $clientSecret,
			'grant_type' => 'password',
			'email' => $_POST['email'],
			'password' => $_POST['password']
		);
		$response = $stagebloc->post('oauth2/token/index', $params);
		
		$response = json_decode($response, true);

		// Put this access token in our config file to make requests with
		$code = file_get_contents('config.php');
		$code = str_replace('<ACCESS TOKEN WILL BE INSERTED HERE>', $response['access_token'], $code);
		file_put_contents('config.php', $code);
	}
}

if ( ! $loginRequired )
{
	// If the data file is empty, we haven't gotten any accounts yet
	// To force reloading of your accounts, simpley empty this file
	if ( ! file_exists('data.txt') || ! file_get_contents('data.txt') )
	{
		$authorizedAccountsJSON = $stagebloc->post('accounts/list', array());
		file_put_contents('data.txt', $authorizedAccountsJSON);
	}

	// We should always have accounts by now since we populated them if the data file was empty
	$accounts = json_decode(file_get_contents('data.txt'));
	$accounts = $accounts->response->items;

	$accountOptionsHTML = '<select name="account" id="account">';
	$accountToUse = ( isset($_COOKIE['account']) ? $_COOKIE['account'] : $accounts[0]->item->id ); // Default to use the first account
	foreach ( $accounts as $account ) // Build a <select> drop down to use to switch between themes quickly
	{
		$accountOptionsHTML .= '<option value="' . $account->item->id . '" ' . ( $accountToUse == $account->item->id ? 'selected' : '' ) . '>' . $account->item->name . '</option>';

		if ( $accountToUse == $account->item->id )
		{
			$accountUrl = $account->item->stagebloc_url;
		}
	}
	$accountOptionsHTML .= '</select>';

	// Find all of the available themes we have
	$themes = array_values(preg_grep('/^([^.])/', scandir('themes/'))); // Ignore hidden files
	$themeOptionsHTML = '<select name="theme" id="theme">';
	$themeToUse = ( isset($_COOKIE['theme']) ? $_COOKIE['theme'] : $themes[0] ); // Default to use the first theme
	foreach ( $themes as $theme ) // Build a <select> drop down to use to switch between themes quickly
	{
		if ( is_dir('themes/' . $theme) )
		{
			$themeOptionsHTML .= '<option value="' . $theme . '" ' . ( $themeToUse == $theme ? 'selected' : '' ) . '>' . $theme . '</option>';
		}
	}
	$themeOptionsHTML .= '</select>';
}
?>

<html>
	<head>
		<title>StageBloc Local Theme Development</title>

		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.7/jquery.min.js"></script>
		<script src="assets/js/jquery.cookie.min.js"></script>
		<script src="assets/js/main.js"></script>
		<script src="assets/bender/js/bender.js"></script>
				
		<link rel="stylesheet" type="text/css" href="assets/bender/css/bender.css" />
		<link rel="stylesheet" type="text/css" href="assets/css/main.css" />
	</head>

	<body>
		<?php if ( ! file_exists('config.php') ): ?>
			<div class="content">
				<article>
					<h2>Error!</h2>
					<p>No config file found. Please rename config-sample.php to config.php.</p>
				</article>
			</div>
		<?php elseif ( $loginRequired ): ?>
			<header><h1><a href="" id="stagebloc-logo">StageBloc</a></h1></header>
			
			<form method="post" id="contact-form" autocomplete="off">
				<fieldset>
					<input class="input" type="text" id="email" name="email" placeholder="Email" required />
					<input class="input" type="password" id="password" name="password" placeholder="Password" required />
					<button type="submit" name="submit">Connect</button>
				</fieldset>
			</form>
		<?php else: ?>
			<div id="console">
				<a href="http://stagebloc.com/developers/theming" target="_blank" class="docs"><i></i>Theming Engine Documentation &rarr;</a>
				<form>
					<?php echo $themeOptionsHTML; ?>
					<?php echo $accountOptionsHTML; ?>
				</form>
			</div>

			<a href="theme_view.php" id="close" title="Close this frame"></a>

			<iframe id="renderedTheme" src="theme_view.php" frameborder="0"></iframe>
		<?php endif; ?>
	</body>
</html>