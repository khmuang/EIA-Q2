<?php
session_start();
if (!isset($_POST["submit"])) {
    header("location: login.php");
    exit;
}

$username = strtolower(trim($_POST["username"]));
$pos = strpos($username, "@");
if ($pos !== false) {
    $username = substr($username, 0, $pos);
}
$pos = strpos($username, "\\");
if ($pos !== false) {
    $username = substr($username, $pos + 1, strlen($username) - $pos);
}
// echo $username;
$_SESSION["username"] = $username;
$loginpassword = trim($_POST["password"]);

require_once "config/cgitasset.php";
try {
    $checkuser = $conn->prepare("SELECT name,domain,bu,role FROM users WHERE name = :loginname AND status=1");
    $checkuser->bindParam(":loginname", $username);
    $checkuser->execute();
    $row = $checkuser->fetch(PDO::FETCH_ASSOC);
    if (strtolower($row["name"]) == $username) {
        $_SESSION["domain"] = trim($row["domain"]);
        $_SESSION["buowner"] = trim($row["bu"]);
        $_SESSION["userrole"] = trim($row["role"]);

        // ob_start();
        require("inc/adauthen.php");
        $chkldapresult = chkldapuser("central", $username, $loginpassword);
        // echo $chkldapresult;
        switch ($chkldapresult) {
            case "Not found":
                $_SESSION["loginstatus"] = "Cannot connect to authentication server, Please check your network connection.";
                break;
            case "Not connect":
                $_SESSION["loginstatus"] = "Cannot authenticate with server, Please contact system administrator.";
                break;
            case "Invalid":
                $_SESSION["loginstatus"] = "User name or password is incorrect. Please try again.";
                break;
            case "Pass":
                $_SESSION["loginstatus"] = "succeeded";
        }
    } else {
        $_SESSION["loginstatus"] = "User name does not exist, please contact system administrator.";
    }
} catch (PDOException $e) {
    $_SESSION["loginstatus"] = "Cannot connect to database server." . $e->getMessage();
}
// echo "Login Status is ".$_SESSION["loginstatus"];
if ($_SESSION["loginstatus"] === "succeeded") {
    include("updateuser.php");
    header("location: dashboard.php");
    exit;
} else {
    header("location: login.php");
    exit;
}
?>