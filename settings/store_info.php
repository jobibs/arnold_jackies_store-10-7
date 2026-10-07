<?php

if (!function_exists('getStoreInfo')) {

    function getStoreInfo(PDO $pdo): array
    {
        static $storeInfo = null;

        if ($storeInfo !== null) {
            return $storeInfo;
        }

        // Default store information
        $storeInfo = [
            'store_name'     => "Arnold & Jackie's Store",
            'store_address'  => '',
            'contact_number' => '',
            'email_address'  => ''
        ];

        try {

            $stmt = $pdo->query("
                SELECT
                    store_name,
                    store_address,
                    contact_number,
                    email_address
                FROM store_settings
                WHERE id = 1
                LIMIT 1
            ");

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {

                if (
                    isset($row['store_name']) &&
                    trim($row['store_name']) !== ''
                ) {
                    $storeInfo['store_name'] = trim($row['store_name']);
                }

                $storeInfo['store_address'] = trim(
                    $row['store_address'] ?? ''
                );

                $storeInfo['contact_number'] = trim(
                    $row['contact_number'] ?? ''
                );

                $storeInfo['email_address'] = trim(
                    $row['email_address'] ?? ''
                );
            }
        } catch (PDOException $e) {

            // Use default values if the table is unavailable
            error_log(
                'Store information error: ' . $e->getMessage()
            );
        }

        return $storeInfo;
    }
}
