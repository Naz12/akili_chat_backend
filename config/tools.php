<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Token-equivalent cost per tool action (for subscription metering)
    |--------------------------------------------------------------------------
    */
    'usage' => [
        'presentation' => [
            'outline_cost' => (int) env('TOOL_PRESENTATION_OUTLINE_COST', 500),
            'content_cost' => (int) env('TOOL_PRESENTATION_CONTENT_COST', 500),
            'export_cost' => (int) env('TOOL_PRESENTATION_EXPORT_COST', 1000),
        ],
        'diagram' => [
            'cost' => (int) env('TOOL_DIAGRAM_COST', 300),
        ],
        'doc_converter' => [
            'cost' => (int) env('TOOL_DOC_CONVERTER_COST', 200),
        ],
    ],
];
