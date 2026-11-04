<?php

return [
    'huggingface' => [
        'token' => secret('hf_api_token', ''),
        // The current Inference endpoint (the old api-inference.huggingface.co URL is retired).
        'url' => env('HF_INFERENCE_URL', 'https://router.huggingface.co/hf-inference/models'),
        'sentiment_model' => env('HF_SENTIMENT_MODEL', 'cardiffnlp/twitter-roberta-base-sentiment-latest'),
        'timeout' => (int) env('HF_TIMEOUT', 15),
    ],
];
