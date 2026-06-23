<?php

return [

  'paths' => [
    resource_path('views'),
  ],

  /*
   * Do not use realpath() here — on first deploy the directory may not exist yet
   * and config:cache would bake in a null compiled path (ViewClearCommand fails).
   */
  'compiled' => env(
    'VIEW_COMPILED_PATH',
    storage_path('framework/views'),
  ),

];
