<?php

namespace App\requestBodies;
/**
 * @OA\Schema(
 *      title="User login request body",
 *      description="User login all request variable",
 *      type="object",
 * )
 */
class LoginRequest
{
    /**
     *	@OA\Property(
     *		title="email",
     *		description="email of user"
     *	)
     *
     * @var string
     */
    protected $email;
    
    /**
     *	@OA\Property(
     *		title="password",
     *		description="password of user"
     *	)
     *
     * @var string
     */
    
    protected $password;
}
