<?php



// Takes a .pem file and returns a string representation of it
function pem2string($pem_file)
{
    $pem = file_get_contents($pem_file);
    $pem = preg_replace('/\s+/', '', $pem);
    // $pem = str_replace('-----BEGINPRIVATEKEY-----', '', $pem);
    // $pem = str_replace('-----ENDPRIVATEKEY-----', '', $pem);
    return $pem;
}


// check first argument for php file
if (isset($argv[1])) {
    $pem_file = $argv[1];

    // check if file exists
    if (file_exists($pem_file)) {
        $pem = pem2string($pem_file);

        // exec("echo $pem | openssl x509 -noout -text", $output);

        // $output = implode("\n", $output);

        // $output = preg_replace('/\s+/', ' ', $output);

        // file_put_contents("output.txt", "VAR=" . $output);
        file_put_contents("output.txt", $pem);

        exec("cat output.txt | pbcopy");

        echo "File generated.\n";

    } else {
        echo "File not found.\n";
        die();
    }


} else {
    echo "No Certificates file provided.\n";
    die();
}