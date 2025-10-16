<?php

namespace App\Services;

use Google\Client;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Firestore Service
 *
 * Helper class to interact with Firebase Firestore database via REST API
 * Used for cleaning up abandoned livestream sessions
 */
class FirebaseService
{
    private $projectId;
    private $databaseId = '(default)'; // Default Firestore database ID

    public function __construct()
    {
        // Get project ID from Firebase credentials
        $contents = File::get(base_path('googleCredentials.json'));
        $json = json_decode($contents, true);
        $this->projectId = $json['project_id'];
    }

    /**
     * Get authenticated access token for Firestore API
     *
     * @return string Access token
     */
    private function getAccessToken()
    {
        $client = new Client();
        $client->setAuthConfig(base_path('googleCredentials.json'));
        $client->addScope('https://www.googleapis.com/auth/datastore');
        $client->fetchAccessTokenWithAssertion();
        $accessToken = $client->getAccessToken();
        return $accessToken['access_token'];
    }

    /**
     * Check if a document exists in Firestore
     *
     * @param string $collection Collection name (e.g., 'liveHostList')
     * @param string $documentId Document ID (e.g., user ID)
     * @return bool True if document exists
     */
    public function documentExists($collection, $documentId)
    {
        try {
            $accessToken = $this->getAccessToken();

            $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/{$this->databaseId}/documents/{$collection}/{$documentId}";

            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // 200 = document exists, 404 = document not found
            return $httpCode === 200;

        } catch (\Exception $e) {
            Log::error("Firebase documentExists error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get document data from Firestore
     *
     * @param string $collection Collection name
     * @param string $documentId Document ID
     * @return array|null Document data or null if not found
     */
    public function getDocument($collection, $documentId)
    {
        try {
            $accessToken = $this->getAccessToken();

            $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/{$this->databaseId}/documents/{$collection}/{$documentId}";

            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return json_decode($result, true);
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Firebase getDocument error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete a document from Firestore
     *
     * @param string $collection Collection name
     * @param string $documentId Document ID
     * @return bool True if deletion successful
     */
    public function deleteDocument($collection, $documentId)
    {
        try {
            $accessToken = $this->getAccessToken();

            $url = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/{$this->databaseId}/documents/{$collection}/{$documentId}";

            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            // 200 = successful deletion
            return $httpCode === 200;

        } catch (\Exception $e) {
            Log::error("Firebase deleteDocument error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete subcollection documents (e.g., comments in a livestream)
     *
     * @param string $collection Parent collection name
     * @param string $documentId Parent document ID
     * @param string $subcollection Subcollection name
     * @return int Number of documents deleted
     */
    public function deleteSubcollection($collection, $documentId, $subcollection)
    {
        try {
            $accessToken = $this->getAccessToken();

            // List all documents in subcollection
            $listUrl = "https://firestore.googleapis.com/v1/projects/{$this->projectId}/databases/{$this->databaseId}/documents/{$collection}/{$documentId}/{$subcollection}";

            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $listUrl);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                return 0; // No documents found or error
            }

            $data = json_decode($result, true);

            if (!isset($data['documents']) || empty($data['documents'])) {
                return 0; // No documents to delete
            }

            $deletedCount = 0;

            // Delete each document in subcollection
            foreach ($data['documents'] as $doc) {
                $docPath = $doc['name'];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "https://firestore.googleapis.com/v1/{$docPath}");
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $deleteResult = curl_exec($ch);
                $deleteHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($deleteHttpCode === 200) {
                    $deletedCount++;
                }
            }

            return $deletedCount;

        } catch (\Exception $e) {
            Log::error("Firebase deleteSubcollection error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get document update timestamp
     *
     * @param string $collection Collection name
     * @param string $documentId Document ID
     * @return string|null Update timestamp or null if not found
     */
    public function getDocumentUpdateTime($collection, $documentId)
    {
        try {
            $document = $this->getDocument($collection, $documentId);

            if ($document && isset($document['updateTime'])) {
                return $document['updateTime'];
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Firebase getDocumentUpdateTime error: " . $e->getMessage());
            return null;
        }
    }
}
