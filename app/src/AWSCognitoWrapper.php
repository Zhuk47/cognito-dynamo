<?php

namespace AWSCognitoApp;

use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\Credentials\Credentials;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;

class AWSCognitoWrapper
{
    private const COOKIE_NAME = 'aws-cognito-app-access-token';

    private $region;
    private $client_id;
    private $userpool_id;
    private $client_secret;
    private $dynamoDB;
    private $client;

    private $user = null;

    public function __construct()
    {
        $this->region = 'us-west-2';
        $this->client_id = '1jiqr604fekclr28poku3epuk9';
        $this->userpool_id = 'us-west-2_Mm7GahcUB';
        $this->client_secret = '631i8mco4nt9ik9cclrhr0lng62kdvq65ponqiob0lfgv3haqbn';
        $this->dynamoDB = new DynamoDbClient([
            'credentials' => [
                'key' => 'AKIAVSABXP73OSVHL4ML',
                'secret' => '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',
            ],
            'region' => 'us-west-2',
            'version' => 'latest'
        ]);
    }

    public function getNotes()
    {
        $notes = $this->dynamoDB->query([
            'TableName' => 'notes',
            'IndexName' => 'userId',
            'KeyConditionExpression' => 'userId = :userId',
            'ExpressionAttributeValues' => [
                ':userId' => [
                    'S' => $this->user->get('Username'),
                ],
            ],
        ])->get('Items');

        return $notes;
    }

    public function addNote($note)
    {
        $marshaler = new Marshaler();

        $tableName = 'notes';
        $userId = $this->user->get('Username');
        $noteId = (string)random_int(100000, 999999);

        $item = $marshaler->marshalJson('
            {
                "userId": "' . $userId . '",
                "noteId": ' . $noteId . ',
                "note": "' . $note . '"
            }
        ');

        $params = [
            'TableName' => $tableName,
            'Item' => $item,
        ];

        $this->dynamoDB->putItem($params);
    }

    public function deleteNote($noteId)
    {
        $marshaler = new Marshaler();

        $tableName = 'notes';

        $key = $marshaler->marshalJson('
            {
                "noteId": ' . $noteId . '
            }
        ');

        $params = [
            'TableName' => $tableName,
            'Key' => $key,
        ];

        $this->dynamoDB->deleteItem($params);
    }

    public function updateNote($noteId, $note)
    {
        $marshaler = new Marshaler();

        $tableName = 'notes';

        $key = $marshaler->marshalJson('
            {
                "noteId": ' . $noteId . '
            }
        ');

        $eav = $marshaler->marshalJson('
            {
                ":note": "' . $note . '"
            }
        ');

        $params = [
            'TableName' => $tableName,
            'Key' => $key,
            'UpdateExpression' =>
                'set note = :note',
            'ExpressionAttributeValues'=> $eav,
            'ReturnValues' => 'UPDATED_NEW',
        ];

        $this->dynamoDB->updateItem($params);
    }

    public function initialize(): void
    {
        $awsCreds = new Credentials('AKIAVSABXP73OSVHL4ML', 'vC0upM7J+Ev7spqyWqt98fZh++VPyiCaEhxazuxS');
        $this->client = new CognitoIdentityProviderClient([
            'credentials' => $awsCreds,
            'version' => 'latest',
            'region' => $this->region,
        ]);

        try {
            $this->user = $this->client->getUser([
                'AccessToken' => $this->getAuthenticationCookie()
            ]);
        } catch (\Exception  $e) {
            // an exception indicates the accesstoken is incorrect - $this->user will still be null
        }
    }

    public function authenticate(string $username, string $password): string
    {
        try {
            $result = $this->client->adminInitiateAuth([
                'AuthFlow' => 'ADMIN_NO_SRP_AUTH',
                'ClientId' => $this->client_id,
                'UserPoolId' => $this->userpool_id,
                'AuthParameters' => [
                    'USERNAME' => $username,
                    'PASSWORD' => $password,
                    'SECRET_HASH' => $this->hash($username),
                ],
            ]);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        $this->setAuthenticationCookie($result->get('AuthenticationResult')['AccessToken']);

        return '';
    }

    public function hash($username)
    {
        $message = $username . $this->client_id;
        $hash = hash_hmac(
            'sha256',
            $message,
            $this->client_secret,
            true
        );

        return base64_encode($hash);
    }

    public function signup(string $username, string $email, string $password): string
    {
        try {
            $result = $this->client->signUp([
                'ClientId' => $this->client_id,
                'Username' => $email,
                'Password' => $password,
                'SecretHash' => $this->hash($email),
                'UserAttributes' => [
                    [
                        'Name' => 'name',
                        'Value' => $username
                    ],
                    [
                        'Name' => 'email',
                        'Value' => $email
                    ]
                ],
            ]);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return '';
    }

    public function confirmSignup(string $username, string $code): string
    {
        try {
            $result = $this->client->confirmSignUp([
                'ClientId' => $this->client_id,
                'Username' => $username,
                'ConfirmationCode' => $code,
            ]);
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return '';
    }

    public function sendPasswordResetMail(string $email): string
    {
        try {
            $this->client->forgotPassword([
                'ClientId' => $this->client_id,
                'Username' => $email,
                'SecretHash' => $this->hash($email),
            ]);
        } catch (Exception $e) {
            return $e->getMessage();
        }

        return '';
    }

    public function resetPassword(string $code, string $password, string $username): string
    {
        try {
            $this->client->confirmForgotPassword([
                'ClientId' => $this->client_id,
                'ConfirmationCode' => $code,
                'Password' => $password,
                'Username' => $username,
                'SecretHash' => $this->hash($username),
            ]);
        } catch (Exception $e) {
            return $e->getMessage();
        }

        return '';
    }

    public function isAuthenticated(): bool
    {
        return null !== $this->user;
    }

    public function getPoolMetadata(): array
    {
        $result = $this->client->describeUserPool([
            'UserPoolId' => $this->userpool_id,
        ]);

        return $result->get('UserPool');
    }

    public function getPoolUsers(): array
    {
        $result = $this->client->listUsers([
            'UserPoolId' => $this->userpool_id,
        ]);

        return $result->get('Users');
    }

    public function getUser(): ?\Aws\Result
    {
        return $this->user;
    }

    public function logout()
    {
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            unset($_COOKIE[self::COOKIE_NAME]);
            setcookie(self::COOKIE_NAME, '', time() - 3600);
        }
    }

    private function setAuthenticationCookie(string $accessToken): void
    {
        /*
         * Please note that plain-text storage of the access token is insecure and
         * not recommended by AWS. This is only done to keep this example
         * application as easy as possible. Read the AWS docs for more info:
         * http://docs.aws.amazon.com/cognito/latest/developerguide/amazon-cognito-user-pools-using-tokens-with-identity-providers.html
        */
        setcookie(self::COOKIE_NAME, $accessToken, time() + 3600);
    }

    private function getAuthenticationCookie(): string
    {
        return $_COOKIE[self::COOKIE_NAME] ?? '';
    }
}
