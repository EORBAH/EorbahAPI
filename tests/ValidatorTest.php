<?php

namespace Eorbahapi\Tests;

use PHPUnit\Framework\TestCase;
use Eorbahapi\Exceptions\ValidationException;
use Eorbahapi\Validator\BaseModel;
use Eorbahapi\Validator\Field;

class UserModel extends BaseModel
{
    public string $name;
    public int $age;
    public ?string $email = null;

    public static function fields(): array
    {
        return [
            'name' => Field::required()->minLength(3),
            'age' => Field::required()->min(18),
            'email' => Field::optional()->email(),
        ];
    }
}

class AliasModel extends BaseModel
{
    public string $first_name;

    public static function fields(): array
    {
        return [
            'first_name' => Field::required()->alias('firstName'),
        ];
    }
}

class ValidatorTest extends TestCase
{
    public function testBaseModelValidatesTypedPayload(): void
    {
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $_POST = [
            'name' => 'Alice',
            'age' => '25',
            'email' => 'alice@example.com',
        ];

        $model = new UserModel();

        $this->assertSame('Alice', $model->name);
        $this->assertSame(25, $model->age);
        $this->assertSame('alice@example.com', $model->email);
    }

    public function testBaseModelRejectsInvalidRules(): void
    {
        $_POST = [
            'name' => 'Al',
            'age' => '15',
            'email' => 'not-an-email',
        ];

        $this->expectException(ValidationException::class);
        new UserModel();
    }

    public function testBaseModelSupportsAliasFieldMapping(): void
    {
        $_POST = [
            'firstName' => 'Bob',
        ];

        $model = new AliasModel();

        $this->assertSame('Bob', $model->first_name);
    }
}
