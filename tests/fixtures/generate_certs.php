<?php

$cnf = <<<'CNF'
[req]
distinguished_name = req_distinguished_name
x509_extensions = v3_ext
prompt = no
[req_distinguished_name]
CN = Peppol Test Signer
O = Peppol Package Test
C = BE
[v3_ext]
basicConstraints = CA:false
keyUsage = critical,digitalSignature
extendedKeyUsage = clientAuth
subjectKeyIdentifier = hash
CNF;

$cnfPath = sys_get_temp_dir().'/peppol-signer-openssl.cnf';
file_put_contents($cnfPath, $cnf);

$dn = ['commonName' => 'Peppol Test Signer', 'organizationName' => 'Peppol Package Test', 'countryName' => 'BE'];
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'config' => $cnfPath]);
if ($key === false) {
    fwrite(STDERR, 'pkey_new failed: '.openssl_error_string().PHP_EOL);
    exit(1);
}
$csr = openssl_csr_new($dn, $key, ['digest_alg' => 'sha256', 'config' => $cnfPath]);
if ($csr === false) {
    fwrite(STDERR, 'csr_new failed: '.openssl_error_string().PHP_EOL);
    exit(1);
}
$crt = openssl_csr_sign($csr, null, $key, 3650, ['digest_alg' => 'sha256', 'config' => $cnfPath]);
if ($crt === false) {
    fwrite(STDERR, 'csr_sign failed: '.openssl_error_string().PHP_EOL);
    exit(1);
}
openssl_x509_export($crt, $pemCrt);
openssl_pkey_export($key, $pemKey, null, ['config' => $cnfPath]);

$certsDir = __DIR__.'/certs';
if (! is_dir($certsDir)) {
    mkdir($certsDir, 0777, true);
}
file_put_contents($certsDir.'/signer.key', $pemKey);
file_put_contents($certsDir.'/signer.crt', $pemCrt);
unlink($cnfPath);

echo 'ok key='.strlen($pemKey).' crt='.strlen($pemCrt).PHP_EOL;
