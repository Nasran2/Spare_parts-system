<!doctype html>
<html lang="en"><meta charset="utf-8"><title>Confirm maintenance</title>
<body><h1>Confirm maintenance</h1>
<p>This action can change the database. Back up the database before continuing.</p>
<form method="POST" action="{{ request()->url() }}">@csrf
<button type="submit">Run maintenance</button></form></body></html>
