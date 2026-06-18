<?php
$content = file_get_contents('app/Http/Controllers/ReportController.php');
$content = str_replace("        return back()->with('success', 'Manual USD→LKR rate saved.');\n    }\n}\n\n    public function neverSold(Request \$request)", "        return back()->with('success', 'Manual USD→LKR rate saved.');\n    }\n\n    public function neverSold(Request \$request)", $content);
file_put_contents('app/Http/Controllers/ReportController.php', $content);
echo "Fixed EOF syntax\n";
