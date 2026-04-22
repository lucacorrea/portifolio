<?php
namespace App\Services;

/**
 * Minimal WebAuthn implementation for Biometric/FaceID support.
 * Handles the server-side "ceremony" of registration and authentication.
 */
class WebAuthnService extends BaseService {
    
    public function __construct() {
        $db = \App\Config\Database::getInstance()->getConnection();
        
        // Ensure table existence (Fallback if migration didn't run)
        try {
            $db->query("SELECT 1 FROM webauthn_credentials LIMIT 1");
        } catch (\Exception $e) {
            $sql = "CREATE TABLE IF NOT EXISTS webauthn_credentials (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                credential_id VARBINARY(255) NOT NULL,
                public_key TEXT NOT NULL,
                user_handle VARBINARY(64) NOT NULL,
                signature_counter INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_used_at TIMESTAMP NULL,
                device_name VARCHAR(100),
                UNIQUE(credential_id),
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
            )";
            $db->exec($sql);
        }

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
     * Decodes Base64URL (used by browsers) to raw binary.
     */
    private function base64url_decode($data) {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public function verifyRegistration($userId, $clientDataJson, $attestationObject, $parsedData) {
        // 1. Verify Challenge
        $clientData = json_decode($clientDataJson);
        $storedChallenge = $_SESSION['webauthn_challenge'] ?? '';
        
        // Browser sends challenge in Base64URL. Server stored it in Base64.
        $receivedChallengeRaw = $this->base64url_decode($clientData->challenge);
        $storedChallengeRaw = base64_decode($storedChallenge);

        if ($receivedChallengeRaw !== $storedChallengeRaw) {
            throw new \Exception("WebAuthn challenge mismatch during registration. Check session persistence.");
        }

        return $this->repository->saveCredential([
            'usuario_id' => $userId,
            'credential_id' => base64_decode($parsedData['id']),
            'public_key' => $parsedData['publicKey'],
            'user_handle' => base64_decode($parsedData['userHandle'] ?? ''),
            'device_name' => $parsedData['deviceName'] ?? 'Dispositivo Móvel'
        ]);
    }

    public function verifyAuthentication($credentialId, $clientDataJson, $authenticatorData, $signature) {
        $credential = $this->repository->findByCredentialId(base64_decode($credentialId));
        if (!$credential) {
            throw new \Exception("Credencial biométrica não encontrada.");
        }

        // 1. Verify Challenge
        $clientData = json_decode($clientDataJson);
        $storedChallenge = $_SESSION['webauthn_challenge'] ?? '';
        
        $receivedChallengeRaw = $this->base64url_decode($clientData->challenge);
        $storedChallengeRaw = base64_decode($storedChallenge);

        if ($receivedChallengeRaw !== $storedChallengeRaw) {
             throw new \Exception("WebAuthn challenge mismatch during authentication.");
        }

        // 2. Reconstruct the signed data
        $clientDataHash = hash('sha256', $clientDataJson, true);
        $authenticatorDataBinary = base64_decode($authenticatorData);
        $dataToVerify = $authenticatorDataBinary . $clientDataHash;

        // 3. Verify Signature
        $publicKey = $credential['public_key'];
        $signatureBinary = base64_decode($signature);
        $derSignature = $this->rawToDer($signatureBinary);

        $result = openssl_verify($dataToVerify, $derSignature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($result === 1) {
            $this->repository->updateCounter($credential['id'], 0);
            return $credential['usuario_id'];
        }

        return false;
    }

    private function rawToDer($signature) {
        $length = strlen($signature);
        $half = $length / 2;
        $r = substr($signature, 0, $half);
        $s = substr($signature, $half);

        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");
        
        if (ord($r[0]) > 0x7f) $r = "\x00" . $r;
        if (ord($s[0]) > 0x7f) $s = "\x00" . $s;

        $rDer = "\x02" . chr(strlen($r)) . $r;
        $sDer = "\x02" . chr(strlen($s)) . $s;
        
        return "\x30" . chr(strlen($rDer . $sDer)) . $rDer . $sDer;
    }
}
