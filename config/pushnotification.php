<?php

// Dry Run true for Sand box mode on ( Local )
// Dry Run false for Sand box mode off ( Production )

return [
  'gcm' => [
      'priority' => 'normal',
      'dry_run' => true,
      'apiKey' => '738138744271',
  ],
  'fcm' => [
        'priority' => 'normal',
        'dry_run' => true,
        'apiKey' => 'AAAATtzzZAg:APA91bE_Tum1FO5mHFt14kQrAx1365qtmbFweJ6XG26ETb1YC_w8BVvNTdG-uYThZCL5kBCkcs4B5t3RdoRzm2f211dNke0R76h2PZTGKczhn-mZoO6VoVnO6f8cZMkSiG8CjUt6zBVi',
        'assistantApiKey' => '338714387464'
  ],
  'apn' => [
      'certificate' => __DIR__ . '/iosCertificates/Certificates.pem',
      'passPhrase' => '', //Optional
      //'passFile' => __DIR__ . '/iosCertificates/yourKey.pem', //Optional
      'dry_run' => true
  ]
];

