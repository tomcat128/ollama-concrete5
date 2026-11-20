<?php
// Prints "installed" if the Ollama Chat package is installed. Run with: concrete/bin/concrete c5:exec
use Concrete\Core\Package\PackageService;
use Concrete\Core\Support\Facade\Application;

$package = Application::getFacadeApplication()->make(PackageService::class)->getByHandle('ollama_chat');
echo ($package && $package->isPackageInstalled()) ? "installed\n" : "missing\n";

// c5:exec uses the return value as exit code; without it, include() returns 1.
return 0;
