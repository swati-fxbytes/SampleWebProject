<?php

namespace App\requestBodies;
/**
 * @OA\Schema(
 *      title="User Registration request body",
 *      description="User registration all request variable",
 *      type="object",
 * )
 */
class RegisterRequest
{
   /**
     *	@OA\Property(
     *		title="firstname",
     *		description="firstname of user"
     *	)
     *
     * @var string
     */
    protected $firstname;
   
   /**
     *	@OA\Property(
     *		title="lastname",
     *		description="lastname of user"
     *	)
     *
     * @var string
     */
    protected $lastname;
    
    /**
     *	@OA\Property(
     *		title="user_type",
     *		description="Role of the user"
     *	)
     *
     * @var integer
     */

    protected $user_type;
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

    /**
     *	@OA\Property(
     *		title="confirm_password",
     *		description="Confirm password"
     *	)
     *
     * @var string
     */
    
    protected $confirm_password;
   

}
