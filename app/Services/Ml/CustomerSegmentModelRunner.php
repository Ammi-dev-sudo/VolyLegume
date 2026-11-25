<?php

namespace App\Services\Ml;

/**
 * Exécute le script Python de segmentation clients (k-means).
 */
class CustomerSegmentModelRunner extends PythonModelRunner
{
    protected function script(): string
    {
        return 'ml/customer_segment_model.py';
    }
}
