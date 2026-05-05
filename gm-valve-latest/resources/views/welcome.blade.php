<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>GM Valve</title>

<link rel="icon" type="image/png" href="{{ asset('uploads/image/fav-icon.png') }}">

<style>
body{
    margin:0;
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background:url("{{ asset('uploads/image/gm-valve.png') }}") no-repeat center center;
    background-size:cover;      /* FULL SCREEN */
    background-attachment:fixed;
    font-family:Arial, Helvetica, sans-serif;
}

.redirect-btn{
    padding:14px 30px;
    background:#fff;
    color:#2257b2;
    font-size:16px;
    border-radius:6px;
    text-decoration:none;
    font-weight:600;
}

.redirect-btn:hover{
    background:#fff;
}
</style>
</head>

<body>

<a href="https://plan.gmvalve.in/login" class="redirect-btn">
Go to Login
</a>

</body>
</html>