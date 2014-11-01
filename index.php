<!doctype html>
<html>
	<head>
		<title>StageBloc Local Theme Development</title>

		<script src="//ajax.googleapis.com/ajax/libs/jquery/1.7/jquery.min.js"></script>
		<script src="assets/js/jquery.cookie.min.js"></script>
		<script src="assets/js/main.js"></script>
		<script src="assets/bender/js/bender.js"></script>

		<link rel="stylesheet" href="assets/bender/css/bender.css" />
		<link rel="stylesheet" href="assets/css/main.css" />
	</head>

	<body>

<?php

$loginRequired = $error = false;

define('GIT_STATUS_CMD', 'git status -uno');
define('GITHUB_COMPARE_URL', 'https://github.com/%s/%s/compare/%s...%s');

// Check to see if they've configured their application
if ( file_exists('config.php') )
{
	require_once 'config.php';

	if ( $accessToken === null )
	{
		$loginRequired = true;

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

				try
				{
					// Attempt to login with the given email and password
					$response = $stagebloc->post('oauth2/token/index', $params);
					$response = json_decode($response, true);
					$accessToken = $response['access_token']; // Override the config var so that we can use it below right away
					$stagebloc->setAccessToken($accessToken);

					// Put this access token in our config file to make requests with
					$code = file_get_contents('config.php');
					$code = str_replace('$accessToken = null;', '$accessToken = \'' . $accessToken . '\';', $code);
					file_put_contents('config.php', $code);
					$loginRequired = false;
				}
				catch ( Services_StageBloc_Invalid_Http_Response_Code_Exception $e )
				{
					$response = json_decode($e->getHttpBody(), true);
					if ( $response && isset($response['response']['errors']) )
					{
						$errors = $response['response']['errors'];
						$error = $errors[0];
					}
					else
					{
						die($e->getHttpBody());
					}
				}
			}
		}
	}
}
else
{
	// Include the StageBloc library if config.php isn't loaded (since it loads it as well)
	require_once 'php-stagebloc-api/StageBloc.php';
}

if ( ! $loginRequired )
{
	// If the data var is null, we haven't gotten any acounts yet
	// To force reloading of your accounts, simply reset this var to null
	if ( $accountData === null )
	{
		try
		{
			$accountData = $stagebloc->post('accounts/list', array());

			// Set the original authenicated account to the one authenciated by the API endpoint
			$accounts = json_decode($accountData);
			$accounts = $accounts->response->items;
			foreach ( $accounts as $account )
			{
				if ( $account->authenticated )
				{
					$_COOKIE['account'] = $account->id; // Since COOKIEs aren't actually set until the next page load, manually set the data temporarily
					setcookie('account', $account->id, time() + 60 * 60 * 24 * 30); // Expire in 30 days
				}
			}

			// Write this data to our config file so that we can read it later instead of retreiving it everytime
			$code = file_get_contents('config.php');
			$accountData = str_replace('\'', '\\\'', $accountData);
			$code = str_replace('$accountData = null', '$accountData = \'' . $accountData . '\'', $code);
			file_put_contents('config.php', $code);
		}
		catch ( Services_StageBloc_Invalid_Http_Response_Code_Exception $e )
		{
			die($e->getHttpBody());
		}
	}

	// We should always have accounts by now since we populated them if the data file was empty
	$accounts = json_decode($accountData);
    $accountOptionsHTML = '<select name="account" id="account"><optgroup label="Accounts">';
    if ( $accounts )
    {
        $accounts = $accounts->response->items;
        $accountUrl = '';

        $accountToUse = ( isset($_COOKIE['account']) ? $_COOKIE['account'] : $accounts[0]->id ); // Default to use the first account
        foreach ( $accounts as $account ) // Build a <select> drop down to use to switch between accounts quickly
        {
            $accountOptionsHTML .= '<option value="' . $account->id . '" ' . ( $accountToUse == $account->id ? 'selected' : '' ) . '>' . $account->name . '</option>';

            if ( $accountToUse == $account->id )
            {
                $accountUrl = $account->stagebloc_url;
            }
        }
    }
    else
    {
        $accountOptionsHTML .= '<option>Please refresh your browser</option>';
    }
    $accountOptionsHTML .= '</optgroup></select>';

	// Find all of the available themes we have
	$themes = array_values(preg_grep('/^([^.])/', scandir($themePath))); // Ignore hidden files
	$themeOptionsHTML = '<select name="theme" id="theme"><optgroup label="Themes">';
	$themeToUse = ( isset($_COOKIE['theme']) ? $_COOKIE['theme'] : $themes[0] ); // Default to use the first theme
	foreach ( $themes as $theme ) // Build a <select> drop down to use to switch between themes quickly
	{
		if ( is_dir($themePath . $theme) )
		{
			$themeOptionsHTML .= '<option value="' . $theme . '" ' . ( $themeToUse == $theme ? 'selected' : '' ) . '>' . $theme . '</option>';
		}
	}
	$themeOptionsHTML .= '</optgroup></select>';
}

/**
 * Joins the output of the git status.
 * @return string
 */
function branchStatus() {
	$status = exec(GIT_STATUS_CMD, $result);
	return implode("\n", $result);
}

/**
 * Checks if the local branch is behind remote.
 * @return boolean
 */
function branchBehindRemote() {
	$status = branchStatus();
	return strpos($status, 'behind');
}

/**
 * Checks if the local branch is ahead of remote.
 * @return boolean
 */
function branchAheadRemote() {
	$status = branchStatus();
	return strpos($status, 'ahead');
}

/**
 * Checks if the local branch is synced with the remote.
 * @return boolean
 */
function branchDifferentFromRemote() {
	return branchBehindRemote() || branchAheadRemote();
}

/**
 * Returns a string with the details of how the branches differ.
 * @return string
 */
function branchCommitsBehindMessage() {
	preg_match("/(behind|ahead).+'(.+)' by (\d+) commit/i", branchStatus(), $matches);

	$position = $matches[1];
	$remote_branch = $matches[2];
	$commits_count = $matches[3];
	$commit_singular_plural = 'commit';

	if( $position == 'ahead' ) {
		$position .= ' of';
	}

	if( $commits_count > 1 ) {
		$commit_singular_plural .= 's';
	}

	return implode(' ', array($commits_count, $commit_singular_plural, $position, $remote_branch));
}

/**
 * Returns the URL to compare the two branches on github
 * @return string
 */
function githubCompareUrl() {
	if( branchAheadRemote() ) {
		return '#';
	}

	$username_repo_regexp = '/origin.+:(.+)\/(.+)\./i';
	exec('git remote -v', $result);
	$result = implode("\n", $result);
	preg_match($username_repo_regexp, $result, $matches);

	$username = $matches[1];
	$repo = $matches[2];
	$sha = exec('git rev-parse --short HEAD');
	$branch = exec('git rev-parse --abbrev-ref HEAD');

	return sprintf(GITHUB_COMPARE_URL, $username, $repo, $sha, $branch);
}

?>

		<?php if ( ! file_exists('config.php') ): ?>
			<div class="content">
				<article>
					<h2>Error!</h2>
					<p>No config file found. Please rename config-sample.php to config.php.</p>
				</article>
			</div>
		<?php elseif ( $loginRequired ): ?>
			<div class="content">
				<header><h1><a href="" id="stagebloc-logo">StageBloc</a></h1></header>

				<form method="post" autocomplete="off">
					<?php if ( $error !== false ): ?>
					<div class="message"><?php echo $error; ?></div>
					<?php endif; ?>
					<fieldset>
						<input class="input" type="text" id="email" name="email" placeholder="Email" required />
						<input class="input" type="password" id="password" name="password" placeholder="Password" required />
						<button type="submit" name="submit">Connect with StageBloc</button>
					</fieldset>
				</form>
			</div>
		<?php else: ?>
			<div id="console">
				<a href="http://stagebloc.com/developers/theming" target="_blank" class="docs"><i></i>Documentation</a>
				<a href="https://stagebloc.com/<?php echo $accountUrl; ?>/admin/management/theme/submit/upload" target="_blank">Upload Assets</a>

				<?php if( branchDifferentFromRemote() ) : ?>
					<a href="<?php echo githubCompareUrl() ?>" target="_blank" class="branch-status"><?php echo branchCommitsBehindMessage() ?></a>
				<?php endif ?>

				<form method="post" action="submit_theme.php" id="updateTheme">
					<?php echo $themeOptionsHTML; ?>
					<?php echo $accountOptionsHTML; ?>
					<select id="actions" name="actions">
						<option value="null">(Actions)</option>
						<option value="reset">Reset Cookies</option>
						<option value="generate">Generate Theme</option>
						<option value="logout">Logout</option>
					</select>
					<select id="mobile" name="mobile">
						<option value="false">Desktop</option>
						<option value="true" <?php echo ( isset($_COOKIE['mobile']) && filter_var($_COOKIE['mobile'], FILTER_VALIDATE_BOOLEAN) ? 'selected' : '' ); ?>>Mobile</option>
					</select>
					<input class="button" type="submit" value="Submit Theme" />
				</form>
			</div>
			<a href="theme_view.php" id="close" title="Close this frame"></a>

			<iframe id="renderedTheme" src="theme_view.php<?php echo ( isset($_GET['url']) ? '?url=' . $_GET['url'] : '' ); ?>" frameborder="0"></iframe>
		<?php endif; ?>
	</body>
</html>
