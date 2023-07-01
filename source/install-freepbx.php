<?php

// Function to make API call to Vault and retrieve MySQL credentials
function getMySQLCredentialsFromVault() {
    // Configure Vault server details
    $vaultAddress = getenv('VAULT_ADDR');
    $vaultToken = getenv('VAULT_TOKEN');

    // Set the path to the Vault endpoint
    $vaultPath = 'database/static-creds/freepbx-role';

    // Set up HTTP headers for the API request
    $headers = [
        'X-Vault-Token: ' . $vaultToken,
    ];

    // Make the API request to Vault
    $ch = curl_init($vaultAddress . '/v1/' . $vaultPath);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    curl_close($ch);

    // Parse the JSON response from Vault
    $responseData = json_decode($response, true);

    // Extract the MySQL credentials from the response
    $mysqlCredentials = $responseData['data'];

    //$username = $responseData['data']['username'];
    $password = $responseData['data']['password'];

	return $password;
    //echo 'Password: ' . $password . "\n";
    //echo gettype($response);

}

$password = getMySQLCredentialsFromVault();

// Run install.php with command-line arguments
$command = 'php /usr/src/freepbx/install -n --dbuser=freepbxuser --dbpass=' . escapeshellarg($password) . ' --dbhost=db';
exec($command);

?>
