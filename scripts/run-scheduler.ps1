$ErrorActionPreference = 'Stop'
$projectDirectory = Split-Path -Parent $PSScriptRoot
$phpExecutable = 'C:\xampp\php\php.exe'
$artisanPath = Join-Path $projectDirectory 'artisan'
$schedulerProcess = Start-Process -FilePath $phpExecutable -ArgumentList @(('"' + $artisanPath + '"'), 'schedule:run') -WorkingDirectory $projectDirectory -WindowStyle Hidden -Wait -PassThru
exit $schedulerProcess.ExitCode
