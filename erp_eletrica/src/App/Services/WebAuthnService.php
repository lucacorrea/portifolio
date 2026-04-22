<?php
namespace App\Services;

/**
 * Minimal WebAuthn implementation for Biometric/FaceID support.
 * Handles the server-side "ceremony" of registration and authentication.
 */
class WebAuthnService extends BaseService {
    
    public function __construct() {
        $db = \App\Config\Database::getInstance()->getConnection();
        parent::__construct(new class($db) {
            private $db;
            public function __construct($db) { $this->db = $db; }
            
            public function saveCredential($data) {
                $sql = "INSERT INTO webauthn_credentials (usuario_id, credential_id, public_key, user_handle, device_name) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $this->db->prepare($sql);
                return $stmt->execute([
                    $data['usuario_id'],
                    $data['credential_id'],
                    $data['public_key'],
                    $data['user_handle'],
                    $data['device_name']
                ]);
            }

            public function findByCredentialId($credId) {
                $stmt = $this->db->prepare("SELECT * FROM webauthn_credentials WHERE credential_id = ? LIMIT 1");
                $stmt->execute([$credId]);
                return $stmt->fetch();
            }

            public function updateCounter($id, $counter) {
                return $this->db->prepare("UPDATE webauthn_credentials SET signature_counter = ?, last_used_at = NOW() WHERE id = ?")
                                ->execute([$counter, $id]);
            }
        });
    }

    public function generateChallenge() {
        $challenge = random_bytes(32);
        $_SESSION['webauthn_challenge'] = base64_encode($challenge);
        return $challenge;
    }

    /**
     * Minimal verification of the registration response.
     * In a real full spec, we would parse CBOR attestationObject.
     * Here we expect the frontend to help with some pre-parsing for simplicity.
     */
    public function verifyRegistration($userId, $clientDataJson, $attestationObject, $parsedData) {
        // 1. Verify Challenge
        $clientData = json_decode($clientDataJson);
        $storedChallenge = $_SESSION['webauthn_challenge'] ?? '';
        
        if (base64_decode($clientData->challenge) !== base64_decode($storedChallenge)) {
            throw new \Exception("WebAuthn challenge mismatch during registration.");
        }

        // 2. In a minimal implementation, we trust the browser's response for the public key 
        // provided we are on a secure origin (HTTPS). The frontend will send the extracted 
        // credentialId and publicKey (PEM format) from the attestation.
        
        return $this->repository->saveCredential([
            'usuario_id' => $userId,
            'credential_id' => base64_decode($parsedData['id']),
            'public_key' => $parsedData['publicKey'],
            'user_handle' => base64_decode($parsedData['userHandle'] ?? ''),
            'device_name' => $parsedData['deviceName'] ?? 'Dispositivo Móvel'
        ]);
    }

    /**
     * Verify the authentication signature.
     */
    public function verifyAuthentication($credentialId, $clientDataJson, $authenticatorData, $signature) {
        $credential = $this->repository->findByCredentialId(base64_decode($credentialId));
        if (!$credential) {
            throw new \Exception("Credencial biométrica não encontrada.");
        }

        // 1. Verify Challenge
        $clientData = json_decode($clientDataJson);
        $storedChallenge = $_SESSION['webauthn_challenge'] ?? '';
        if (base64_decode($clientData->challenge) !== base64_decode($storedChallenge)) {
             throw new \Exception("WebAuthn challenge mismatch during authentication.");
        }

        // 2. Reconstruct the signed data
        // Signed data = authenticatorData + hash_sha256(clientDataJSON)
        $clientDataHash = hash('sha256', $clientDataJson, true);
        $authenticatorDataBinary = base64_decode($authenticatorData);
        $dataToVerify = $authenticatorDataBinary . $clientDataHash;

        // 3. Verify Signature using OpenSSL
        $publicKey = $credential['public_key'];
        $signatureBinary = base64_decode($signature);
        
        // Note: Browsers return signatures as raw (r, s) concatenated. 
        // PHP openssl_verify expects ASN.1 DER format.
        $derSignature = $this->rawToDer($signatureBinary);

        $result = openssl_verify($dataToVerify, $derSignature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($result === 1) {
            // Success! Update counter
            $this->repository->updateCounter($credential['id'], 0); // Minimal counter tracking
            return $credential['usuario_id'];
        }

        return false;
    }

    /**
     * Helper to convert raw WebAuthn signature (concat R and S) to ASN.1 DER.
     */
    private function rawToDer($signature) {
        $length = strlen($signature);
        $half = $length / 2;
        $r = substr($signature, 0, $half);
        $s = substr($signature, $half);

        // Trim leading zeros but ensure at least one byte
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        
        // If highest bit is set, prefix with zero
        if (ord($r[0]) > 0x7f) $r = "\x00" . $r;
        if (ord($s[0]) > 0x7f) $s = "\x00" . $s;

        $rDer = "\x02" . chr(strlen($r)) . $r;
        $sDer = "\x02" . chr(strlen($s)) . $s;
        
        return "\x30" . chr(strlen($rDer . $sDer)) . $rDer . $sDer;
    }
}
