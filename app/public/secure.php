<?php
require '../vendor/autoload.php';

use AWSCognitoApp\AWSCognitoWrapper;

$wrapper = new AWSCognitoWrapper();
$wrapper->initialize();

if(!$wrapper->isAuthenticated()) {
    header('Location: /');
    exit;
}

if(isset($_POST['action'])) {

    $note = $_POST['note'] ?? '';
    $noteId = $_POST['noteId'] ?? '';

    if($_POST['action'] === 'note') {
        $wrapper->addNote($note);
    }

    if($_POST['action'] === 'delete') {
        $wrapper->deleteNote($noteId);
    }

    if($_POST['action'] === 'update') {
        $wrapper->updateNote($noteId, $note);
    }
}

$user = $wrapper->getUser();
$pool = $wrapper->getPoolMetadata();
$users = $wrapper->getPoolUsers();
$notes = $wrapper->getNotes();
?>

<!doctype html>
<html>
    <head>
        <meta charset='utf-8'>
        <meta http-equiv='x-ua-compatible' content='ie=edge'>
        <title>AWS Cognito App - Register and Login</title>
        <meta name='viewport' content='width=device-width, initial-scale=1'>
    </head>
    <body>
        <h1>Menu</h1>
        <ul>
            <li><a href='/'>Index</a></li>
            <li><a href='/secure.php'>Secure page</a></li>
            <li><a href='/confirm.php'>Confirm signup</a></li>
            <li><a href='/forgotpassword.php'>Forgotten password</a></li>
            <li><a href='/logout.php'>Logout</a></li>
        </ul>
        <h1>Secure page</h1>
        <p>Welcome <strong><?php echo $user->get('Username');?></strong>! You are successfully authenticated. Some <em>secret</em> information about this user pool:</p>

        <h2>Metadata</h2>
        <p><b>Id:</b> <?php echo $pool['Id'];?></p>
        <p><b>Name:</b> <?php echo $pool['Name'];?></p>
        <p><b>CreationDate:</b> <?php echo $pool['CreationDate'];?></p>

        <h2>Users</h2>
        <ul>
        <?php
        foreach($users as $user) {
            $email_attribute_index = array_search('email', array_column($user['Attributes'], 'Name'));
            $email = $user['Attributes'][$email_attribute_index]['Value'];

            echo "<li>{$user['Username']} ({$email})</li>";
        }
        ?>
        </ul>
        <h2>Notes</h2>
        <h5>(from DynamoDB)</h5>
        <ul>
        <?php
        foreach($notes as $note) {
            echo '<li>';
            echo "<form method='post' action=''>
                      <input hidden type='text' name='noteId' value='{$note['noteId']['N']}'/>
                      <input type='text' name='note' value='{$note['note']['S']}'/>
                      <input type='hidden' name='action' value='update' />
                      <input type='submit' value='Update'/>
                  </form>";
            echo "<form method='post' action=''>
                      <input hidden type='text' name='noteId' value='{$note['noteId']['N']}'/>
                      <input type='hidden' name='action' value='delete' />
                      <input type='submit' value='X'/>
                  </form>";
            echo '</li>';
        }
        ?>
        </ul>
        <form method='post' action=''>
            <input type='text' placeholder='Note' name='note' /><br />
            <input type='hidden' name='action' value='note' />
            <input type='submit' value='Create item' />
        </form>
    </body>
</html>
