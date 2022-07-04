<?php

namespace App\Api\V1\Controllers\Application;

use App\Api\V1\Controllers\Controller;
use App\Models\User;
use App\Services\SendEmailNotify;
use App\Traits\TokenHandler;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Class UserProfileController
 *
 * @package App\Api\V1\Controllers\User
 */
class UserProfileController extends Controller
{
    use TokenHandler;

    /**
     * Saving user full person detail
     *
     * @OA\Post(
     *     path="/user-profile",
     *     summary="Saving user person detail",
     *     description="Saving user person detail",
     *     tags={"User Profile"},
     *
     *     security={{
     *         "passport": {
     *             "User",
     *             "ManagerRead",
     *             "ManagerWrite"
     *         }
     *     }},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UserProfile")
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Successfully save"
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="Identity verification session successfully initialized"
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid request"
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not Found"
     *     ),
     *     @OA\Response(
     *         response="422",
     *         description="Validation failed"
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Unknown error"
     *     )
     * )
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function store(Request $request): mixed
    {
        // Validate input
        try {
            $this->validate($request, User::personValidationRules());
        } catch (ValidationException $e) {
            return response()->jsonApi([
                'type' => 'warning',
                'title' => 'User person details data',
                'message' => "Validation error",
                'data' => $e->getMessage()
            ], 400);
        }

        // Try to save received data
        try {
            // Get user_id as user_Id
            $user_id = Auth::user()->getAuthIdentifier();

            // Find exist user
            $user = User::find($user_id);

            // If not exist, then to create it
            if (!$user) {
                // Create new
                $user = User::create([
                    'id' => $user_id,
                    'status' => User::STATUS_STEP_1
                ]);
            }

            // Convert address field and save person data
            $personData = $request->all();
            foreach ($personData['address'] as $key => $value) {
                $personData['address_' . $key] = $value;
            }
            unset($personData['address']);

            $user->fill($personData);
            $user->status = User::STATUS_STEP_2;
            $user->save();

            // Return response to client
            return response()->jsonApi([
                'type' => 'success',
                'title' => 'New user registration',
                'message' => "User person detail data successfully saved",
                'data' => $user->toArray()
            ], 200);
        } catch (Exception $e) {
            return response()->jsonApi([
                'type' => 'danger',
                'title' => 'Saving user personal data',
                'message' => $e->getMessage(),
                'data' => null
            ], 400);
        }
    }

    /**
     * Get current user profile data
     *
     * @OA\Get(
     *     path="/user-profile/me",
     *     summary="Get current user profile data",
     *     description="Get current user profile data",
     *     tags={"User Profile"},
     *
     *     security={{
     *         "passport": {
     *             "User",
     *             "ManagerRead"
     *         }
     *     }},
     *
     *     @OA\Response(
     *          response="201",
     *          description="Success",
     *
     *          @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="success"
     *             ),
     *             @OA\Property(
     *                 property="title",
     *                 type="string",
     *                 example="Get current user profile data"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="User profile data retrieved successfully."
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="User object",
     *
     *                 @OA\Property(
     *                     property="first_name",
     *                     type="string",
     *                     example="John"
     *                 ),
     *                 @OA\Property(
     *                     property="last_name",
     *                     type="string",
     *                     example="Kiels"
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     example="Kiels@onestep.com"
     *                 ),
     *                 @OA\Property(
     *                     property="country",
     *                     type="string",
     *                     example="United Kindom"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *          response="400",
     *          description="Bad Request",
     *
     *          @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="danger"
     *             ),
     *             @OA\Property(
     *                 property="title",
     *                 type="string",
     *                 example="Get current user profile data"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="User data not found."
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="User object",
     *             )
     *         )
     *     )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
//        $user = Auth::user();
//        if ($user) {}

        try {
            $builder = User::where('id', Auth::user()->id);

            // Check whether user already exist
            if ($builder->exists()) {
                // Add relations to object
                $user = new User();
                if ($includes = $request->get('include')) {
                    foreach (explode(',', $includes) as $include) {
                        if (method_exists($user, $include) && $user->{$include}() instanceof Relation) {
                            $builder->with($include);
                        }
                    }
                }

                // Fetch user profile
                $user = $builder->select(
                    'first_name',
                    'last_name',
                    'email',
                    'address_country',
                    'locale'
                )->firstOrFail();

                // Return response
                return response()->jsonApi([
                    'type' => 'success',
                    'title' => 'Get current user profile data',
                    'message' => '"User profile retrieved successfully',
                    'data' => $user->toArray(),
                ]);
            } else {
                return response()->json([
                    'type' => 'danger',
                    'message' => "User profile does NOT exist.",
                    "data" => null
                ], 400);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'type' => 'danger',
                'title' => 'Get current user profile data',
                'message' => "Unable to retrieve user profile.",
                "data" => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'type' => 'danger',
                'title' => 'Get current user profile data',
                'message' => $e->getMessage(),
                'data' => []
            ], 404);
        }


        // Get object
//        $user = $this->getObject(Auth::user()->getAuthIdentifier());
//
//        if ($user instanceof JsonApiResponse) {
//            return $user;
//        }
//
//        return response()->jsonApi([
//            'type' => 'success',
//            'title' => 'User details data',
//            'message' => "User detail data has been received",
//            'data' => $user->toArray()
//        ], 200);
    }

    /**
     * Update the specified resource in storage
     *
     * @OA\Patch(
     *     path="/user-profile/{id}",
     *     summary="update user",
     *     description="update user",
     *     tags={"User Profile"},
     *
     *     @OA\Parameter(
     *          description="ID of User",
     *          in="path",
     *          name="id",
     *          required=true,
     *          example="1",
     *          @OA\Schema(
     *              type="integer",
     *              format="int64"
     *          ),
     *     ),
     *
     *     security={{
     *         "passport": {
     *             "User",
     *             "ManagerRead"
     *         }
     *     }},
     *
     *     @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="first_name",
     *                  type="string",
     *                  description="First name",
     *              ),
     *              @OA\Property(
     *                  property="last_name",
     *                  type="string",
     *                  description="Last name",
     *              ),
     *              @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 description="User email for user profile update",
     *                 example="johnkiels@ultainfinity.com"
     *             )
     *              @OA\Property(
     *                  property="phone",
     *                  type="string",
     *                  description="Phone number",
     *              ),
     *              @OA\Property(
     *                  property="birthday",
     *                  type="string",
     *                  description="Date of birth in format DD-MM-YYYY",
     *              ),
     *              @OA\Property(
     *                 property="locale",
     *                 type="string",
     *                 description="Update user profile locale",
     *                 example="UK English"
     *              ),
     *              @OA\Property(
     *                  property="subscribed_to_announcement",
     *                  type="string",
     *                  description="Indicate whether or not the user should be subscribed for announcements",
     *              ),
     *              @OA\Property(
     *                  property="address_country",
     *                  type="string",
     *                  description="Country code",
     *              ),
     *              @OA\Property(
     *                  property="address_line1",
     *                  type="string",
     *                  description="First line of address. may contain house number, street name, etc.",
     *              ),
     *              @OA\Property(
     *                  property="address_line2",
     *                  type="string",
     *                  description="Second line of address.",
     *              ),
     *              @OA\Property(
     *                  property="address_city",
     *                  type="string",
     *                  description="Name of city",
     *              ),
     *              @OA\Property(
     *                  property="address_zip",
     *                  type="string",
     *                  description="Zip code",
     *              ),
     *
     *          ),
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Success"
     *     ),
     *     @OA\Response(
     *          response="201",
     *          description="Success",
     *
     *          @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="success"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Email update was successful"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *          response="400",
     *          description="Bad Request",
     *
     *          @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="danger"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Email update FAILED"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not found"
     *     )
     * )
     *
     * @param Request $request
     * @param int $id
     *
     * @return Response
     * @throws ValidationException
     */
    public function update(Request $request, int $id): Response
    {
        try {
            //validate input date
            $inputData = $this->validate($request, User::profileValidationRules((int)$id));


            // Get User object
            $user = User::findOrFail($id);


            //Update username
            $user->update([
                'email' => $inputData['email']
            ]);

            //Update username
            $user->update([
                'username' => $inputData['username']
            ]);

            //Update full name
            $user->update([
                'first_name' => $inputData['firstname'],
                'last_name' => $inputData['lastname']
            ]);

            //Update username
            $user->update([
                'country' => $inputData['country']
            ]);

            //Update username
            $user->update([
                'locale' => $inputData['locale']
            ]);

            // Update data and save
            $user->fill($inputData);
            $user->save();



            if (!empty($request->email)) {
                $user->status = User::STATUS_ACTIVE;
                $user->verify_token = Str::random(32);

                PubSub::transaction(function () use ($user) {
                    $user->save();
                })->publish('sendVerificationEmail', [
                    'email' => $user->email,
                    'display_name' => $user->display_name,
                    'verify_token' => $user->verify_token,
                ], 'mail');
            }

//        $update = $request->except(['password']);
//
//        if ($request->has('current_password')) {
//            if (Hash::check($request->current_password, $user->password)) {
//                $update['password'] = Hash::make($request->password);
//            } else {
//                throw new BadRequestHttpException('Invalid current_password');
//            }
//        }

            //Show response
            return response()->json([
                'type' => 'success',
                'message' => "Email update was successful."
            ], 400);
        }
        catch (ValidationException $e) {
            return response()->jsonApi([
                'type' => 'danger',
                'title' => 'User profile update',
                'message' => "Validation error: " . $e->getMessage(),
                'data' => null
            ], 400);
        }
        catch (ModelNotFoundException $e) {
            return response()->json([
                'type' => 'danger',
                'message' => "Unable to update email.",
                "data" => $e->getMessage()
            ], 400);

            return response()->json([
                'type' => 'danger',
                'message' => "User profile does NOT exist.",
                "data" => null
            ], 400);
        }
        catch(Exception $e){

        }
    }

    /**
     * Validate the verification code and update phone number
     *
     * @OA\Put(
     *     path="/user-profile/update-phone",
     *     summary="Update current user's phone number",
     *     description="Validate the verification code and update phone number of the current user",
     *     tags={"User Profile"},
     *
     *     security={{
     *         "passport": {
     *             "User",
     *             "ManagerRead"
     *         }
     *     }},
     *
     *     @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="phone",
     *                  type="string",
     *                  description="phone number of the user",
     *              ),
     *              @OA\Property(
     *                  property="verification_code",
     *                  type="string",
     *                  description="verification code previously send",
     *              ),
     *
     *          ),
     *     ),
     *
     *    @OA\Response(
     *        response="200",
     *        description="Validation success",
     *        @OA\JsonContent(
     *           @OA\Property(property="message", type="string", example="Phone number updated"),
     *        )
     *     ),
     *
     *    @OA\Response(
     *        response="500",
     *        description="Validation success",
     *        @OA\JsonContent(
     *           @OA\Property(property="message", type="string", example="An error occurred! Please, try again."),
     *        )
     *     ),
     *
     *    @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *            @OA\Property(property="message", type="string", example="The given data was invalid."),
     *            @OA\Property(
     *               property="errors",
     *               type="object",
     *               @OA\Property(
     *                  property="phone",
     *                  type="array",
     *                  collectionFormat="multi",
     *                  @OA\Items(
     *                     type="string",
     *                     example={"The phone number is already taken.","The phone number is invalid."},
     *                  )
     *               ),
     *
     *               @OA\Property(
     *                  property="verification_code",
     *                  type="array",
     *                  collectionFormat="multi",
     *                  @OA\Items(
     *                     type="string",
     *                     example={"The verification code is invalid."},
     *                  )
     *               ),
     *            )
     *         )
     *      )
     * )
     *
     * @param Request $request
     *
     * @return Response
     * @throws Exception
     */
    public function updatePhone(Request $request): Response
    {
        $rules = [
            'phone' => [
                'required',
                'regex:/\+?\d{7,16}/i',
                "unique:users,phone",
            ],
            'verification_code' => [
                'required',
                'regex:/\d{6}/i',
                function ($attribute, $value, $fail) {
                    $user = User::first(Auth::user()->id);
                    if (!Hash::check($value, $user->verification_code)) {
                        $fail('The verification code is invalid.');
                    }
                },
            ],
        ];

        $validationMessages = [
            'verification_code.regex' => 'The verification code is invalid',
        ];

        $this->validate($request, $rules, $validationMessages);

        try {
            $user = User::first(Auth::user()->id);
            $user->phone = $request->get('phone', null);
            $user->verification_code = null;
            if (!$user->save()) {
                throw new Exception();
            }
            return response()->jsonApi(["message" => "Phone number updated"], 200);
        } catch (Exception $e) {
            return response()->jsonApi(["message" => "An error occurred! Please, try again."], 500);
        }
    }

    /**
     * Change user profile password for One-Step 2.0
     *
     * @OA\Put(
     *     path="/user-profile/update/password",
     *     summary="Change user password",
     *     description="Change user profile password for One-Step 2.0",
     *     tags={"User Profile"},
     *
     *     security={{
     *         "passport": {
     *             "User",
     *             "ManagerRead"
     *         }
     *     }},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="id",
     *                 type="string",
     *                 description="User ID for user profile update",
     *                 required={"true"},
     *                 example="373458be-3f01-40ca-b6f3-245239c7889f"
     *             ),
     *             @OA\Property(
     *                 property="current_password",
     *                 type="string",
     *                 description="Current user password for profile update",
     *                 required={"true"},
     *                 example="XXXXXXXX"
     *             ),
     *             @OA\Property(
     *                 property="new_password",
     *                 type="string",
     *                 description="New user password for profile update",
     *                 required={"true"},
     *                 example="XXXXXXXX"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *          response="201",
     *          description="Success",
     *
     *          @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="success"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="User profile password changed successfully."
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="User object"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *          response="400",
     *          description="Bad Request",
     *
     *          @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="danger"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Unable to change profile password."
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="User object",
     *                 example=""
     *             )
     *         )
     *     )
     * )
     *
     * @param Request $request
     * @param SendEmailNotify $sendEmail
     *
     * @return JsonResponse
     */
    public function updatePassword(Request $request, SendEmailNotify $sendEmail): JsonResponse
    {
        $validData = $this->validate($request, [
            'id' => 'required|string',
            'current_password' => 'required|string|max:32',
            'new_password' => 'required|string|max:32'
        ]);

        try {
            // Verify current password
            $userQuery = User::where('id', $validData['id']);

            $user = $userQuery->firstOrFail();

            if (Hash::check($validData['current_password'], $user->password)) {

                $newPass = Hash::make($validData['new_password']);

                // Update user password
                $userQuery->update([
                    'password' => $newPass
                ]);

                //Send notification email
                $subject = 'Change Password';
                $message = 'Your password has been updated successfully.';
                $sendEmail->dispatchEmail($to['email'], $subject, $message);

                //Show response
                return response()->json([
                    'type' => 'success',
                    'message' => "User password updated successfully.",
                    "data" => null
                ], 200);

            } else {
                return response()->json([
                    'type' => 'danger',
                    'message' => "Invalid user password. Try again",
                    "data" => null
                ], 400);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'type' => 'danger',
                'message' => "Unable to update user password.",
                "data" => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update username for One-Step 2.0
     *
     * @OA\Put(
     *     path="/user-profile/update/username",
     *     summary="Update username",
     *     description="Update username for One-Step 2.0",
     *     tags={"User Profile"},
     *
     *     security={{
     *         "passport": {
     *             "User",
     *             "ManagerRead"
     *         }
     *     }},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="username",
     *                 type="string",
     *                 description="User username for user profile update",
     *                 example="john.kiels"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *          response="201",
     *          description="Success",
     *
     *          @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="success"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Username update was successful"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *          response="400",
     *          description="Bad Request",
     *
     *          @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="danger"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Username update FAILED"
     *             )
     *         )
     *     )
     * )
     *
     * @param Request $request
     * @param SendEmailNotify $sendEmail
     *
     * @return JsonResponse
     * @throws ValidationException
     */
    public function updateUsername(Request $request, SendEmailNotify $sendEmail): JsonResponse
    {
        //validate input date
        $input = $this->validate($request, [
            'username' => 'required|string'
        ]);

        try {
            // Check whether user already exist
            $userQuery = User::where([
                'username' => $input['username']
            ]);

            if ($userQuery->exists()) {

                $user = $userQuery->firstOrFail();

                //Send notification email
                $subject = 'Change Username';
                $message = 'Your username has been updated successfully.';

                $sendEmail->dispatchEmail($to['email'], $subject, $message);

                //Show response
                return response()->json([
                    'type' => 'success',
                    'message' => "Username update was successful."
                ], 400);
            } else {
                return response()->json([
                    'type' => 'danger',
                    'message' => "User profile does NOT exist.",
                    "data" => null
                ], 400);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'type' => 'danger',
                'message' => "Unable to update Username.",
                "data" => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update username for One-Step 2.0
     *
     * @OA\Put(
     *     path="/user-profile/update/fullname",
     *     summary="Update fullname",
     *     description="Update profile fullname",
     *     tags={"User Profile"},
     *
     *     security={{
     *         "passport": {
     *             "User",
     *             "ManagerRead"
     *         }
     *     }},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="id",
     *                 type="string",
     *                 description="User ID for user profile update",
     *                 required={"true"},
     *                 example="373458be-3f01-40ca-b6f3-245239c7889f"
     *             ),
     *             @OA\Property(
     *                 property="firstname",
     *                 type="string",
     *                 description="User firstname for user profile update",
     *                 example="john"
     *             ),
     *             @OA\Property(
     *                 property="lastname",
     *                 type="string",
     *                 description="User lastname for user profile update",
     *                 example="kiels"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *          response="201",
     *          description="Success",
     *
     *          @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="success"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Full name update was successful"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *          response="400",
     *          description="Bad Request",
     *
     *          @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="danger"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Full name update FAILED"
     *             )
     *         )
     *     )
     * )
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws ValidationException
     */
    public function updateFullname(Request $request): JsonResponse
    {
        try {
            // Check whether user already exist
            $userQuery = User::where(['id' => $input['id']]);

            if ($userQuery->exists()) {

                $user = $userQuery->firstOrFail();

                //Show response
                return response()->json([
                    'type' => 'success',
                    'message' => "Full name update was successful."
                ], 400);
            } else {
                return response()->json([
                    'type' => 'danger',
                    'message' => "User profile does NOT exist.",
                    "data" => null
                ], 400);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'type' => 'danger',
                'message' => "Unable to update Full name.",
                "data" => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update Country for One-Step 2.0
     *
     * @OA\Put(
     *     path="/user-profile/update/country",
     *     summary="Update country",
     *     description="Update country for One-Step 2.0",
     *     tags={"User Profile"},
     *
     *     security={{
     *         "passport": {
     *             "User",
     *             "ManagerRead"
     *         }
     *     }},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *
     *            @OA\Property(
     *                 property="id",
     *                 type="string",
     *                 description="User ID for user profile update",
     *                 required={"true"},
     *                 example="373458be-3f01-40ca-b6f3-245239c7889f"
     *             ),
     *             @OA\Property(
     *                 property="country",
     *                 type="string",
     *                 description="User country for user profile update",
     *                 example="United Kindom"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *          response="201",
     *          description="Success",
     *
     *          @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="success"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Country update was successful"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *          response="400",
     *          description="Bad Request",
     *
     *          @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(
     *                 property="type",
     *                 type="string",
     *                 example="danger"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Country update FAILED"
     *             )
     *         )
     *     )
     * )
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @throws ValidationException
     */
    public function updateCountry(Request $request): JsonResponse
    {
        try {
            //validate input date
            $input = $this->validate($request, [
                'id' => 'required|string',
                'country' => 'required|string'
            ]);

            // Check whether user already exist
            $userQuery = User::where(['id' => $input['id']]);

            if ($userQuery->exists()) {

                $user = $userQuery->firstOrFail();

                //Show response
                return response()->json([
                    'type' => 'success',
                    'message' => "Country update was successful."
                ], 400);
            } else {
                return response()->json([
                    'type' => 'danger',
                    'message' => "User profile does NOT exist.",
                    "data" => null
                ], 400);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'type' => 'danger',
                'message' => "Unable to update country.",
                "data" => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Validate the verification code and update the current user's email
     *
     * @OA\Post(
     *     path="/user-profile/update-email",
     *     summary="Update current user's email",
     *     description="Validate the verification code and update the current user's email",
     *     tags={"User Profile"},
     *
     *     security={{
     *         "passport": {
     *             "User",
     *             "ManagerRead"
     *         }
     *     }},
     *
     *     @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="email",
     *                  type="string",
     *                  description="Email of the user",
     *              ),
     *              @OA\Property(
     *                  property="verification_code",
     *                  type="string",
     *                  description="verification code previously send",
     *              ),
     *
     *          ),
     *     ),
     *
     *    @OA\Response(
     *        response="200",
     *        description="Validation success",
     *        @OA\JsonContent(
     *           @OA\Property(property="message", type="string", example="Email updated"),
     *        )
     *     ),
     *
     *    @OA\Response(
     *        response="500",
     *        description="Validation success",
     *        @OA\JsonContent(
     *           @OA\Property(property="message", type="string", example="An error occurred! Please, try again."),
     *        )
     *     ),
     *
     *    @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *            @OA\Property(property="message", type="string", example="The given data was invalid."),
     *            @OA\Property(
     *               property="errors",
     *               type="object",
     *               @OA\Property(
     *                  property="phone",
     *                  type="array",
     *                  collectionFormat="multi",
     *                  @OA\Items(
     *                     type="string",
     *                     example={"The email is already taken.","The email is invalid."},
     *                  )
     *               ),
     *
     *               @OA\Property(
     *                  property="verification_code",
     *                  type="array",
     *                  collectionFormat="multi",
     *                  @OA\Items(
     *                     type="string",
     *                     example={"The verification code is invalid."},
     *                  )
     *               ),
     *            )
     *         )
     *      )
     * )
     *
     * @param Request $request
     *
     * @return Response
     * @throws Exception
     */
    public function updateMyEmail(Request $request): Response
    {
        $rules = [
            'phone' => [
                'required',
                'email',
                "unique:users,email",
            ],
            'verification_code' => [
                'required',
                'regex:/\d{6}/i',
                function ($attribute, $value, $fail) {
                    $user = User::first(Auth::user()->id);
                    if (!Hash::check($value, $user->verification_code)) {
                        $fail('The verification code is invalid.');
                    }
                },
            ],
        ];

        $validationMessages = [
            'verification_code.regex' => 'The verification code is invalid',
        ];
        $this->validate($request, $rules, $validationMessages);

        try {
            $user = User::first(Auth::user()->id);
            $user->email = $request->email;
            $user->verification_code = null;
            if (!$user->save()) {
                throw new Exception();
            }
            return response()->jsonApi(["message" => "Email updated"], 200);
        } catch (Exception $e) {
            return response()->jsonApi(["message" => "An error occurred! Please, try again."], 500);
        }
    }

    /**
     * Verify user email
     *
     * @OA\Post(
     *     path="/user-profile/verify-email-send",
     *     summary="Verify user email",
     *     description="resend user email",
     *     tags={"User Profile"},
     *
     *     security={{
     *         "passport": {
     *             "User",
     *             "ManagerRead",
     *             "ManagerWrite"
     *         }
     *     }},
     *
     *     @OA\Parameter(
     *          name="email",
     *          required=true,
     *          in="query",
     *          @OA\Schema (
     *              type="string"
     *          )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Success"
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not found"
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Bad Request"
     *     )
     * )
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function verify_email(Request $request): JsonResponse
    {
        $this->validate($request, [
            'email' => "required|email",
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        PubSub::publish('sendVerificationEmail', [
            'email' => $user->email,
            'display_name' => $user->display_name,
            'verify_token' => $user->verify_token,
        ], 'mail');

        return response()->jsonApi(["email sent"], 200);
    }

    /**
     * Validate the new phone number that a user whats to use
     *
     * @OA\Post(
     *     path="/user-profile/validate-edit-phone",
     *     summary="Validate the new user phone number",
     *     description="Validate the new phone number that the current user whats to use",
     *     tags={"User Profile"},
     *
     *     security={{
     *         "passport": {
     *             "User",
     *             "ManagerRead"
     *         }
     *     }},
     *
     *     @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="phone",
     *                  type="string",
     *                  description="phone number of the user",
     *              ),
     *          ),
     *     ),
     *
     *    @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *            @OA\Property(property="message", type="string", example="The given data was invalid."),
     *            @OA\Property(
     *               property="errors",
     *               type="object",
     *               @OA\Property(
     *                  property="phone",
     *                  type="array",
     *                  collectionFormat="multi",
     *                  @OA\Items(
     *                     type="string",
     *                     example={"The phone number is already taken.","The phone number is invalid."},
     *                  ),
     *               ),
     *            ),
     *         ),
     *      ),
     *
     *     @OA\Response(
     *        response="200",
     *        description="Validation success",
     *        @OA\JsonContent(
     *           @OA\Property(property="message", type="string", example="A 6-digit code has been sent to your phone number"),
     *        )
     *     ),
     *
     *    @OA\Response(
     *        response="500",
     *        description="Validation success",
     *        @OA\JsonContent(
     *           @OA\Property(property="message", type="string", example="An error occurred! Please, try again."),
     *        )
     *     )
     * )
     *
     * @param Request $request
     *
     * @return Response
     * @throws Exception
     */
    public function validateEditPhoneNumber(Request $request): Response
    {
        $this->validate($request, [
            'phone' => [
                'required',
                'regex:/\+?\d{7,16}/i',
                "unique:users,phone",
            ],
        ]);

        try {
            $verificationCode = Str::random(6);
            $user = User::first(Auth::user()->id);
            $user->verification_code = Hash::make($verificationCode);

            if (!$user->save()) {
                throw new Exception();
            }

            // Should send SMS to the user's new phone number, contaiing the verification code
            $response = Http::post('[COMMUNICATIONS_MS_URL]/messages/sms/send-message', [
                'to' => $request->get('phone', null),
                'message' => 'Your verification code is: ' . $verificationCode,
            ]);

            if (!$response->ok()) {
                throw new Exception();
            }

            return response()->jsonApi(["message" => "A 6-digit code has been sent to your phone number"], 200);
        } catch (Exception $e) {
            return response()->jsonApi(["message" => "An error occurred! Please, try again."], 500);
        }
    }

    /**
     * Validate the new email that the current user whats to use
     *
     * @OA\Post(
     *     path="/user-profile/validate-edit-email",
     *     summary="Validate the new user email",
     *     description="Validate the new email that the current user whats to use, and send verification code",
     *     tags={"User Profile"},
     *
     *     security={{
     *         "passport": {
     *             "User",
     *             "ManagerRead"
     *         }
     *     }},
     *
     *     @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="email",
     *                  type="string",
     *                  description="email of the user",
     *              ),
     *
     *          ),
     *     ),
     *
     *    @OA\Response(
     *        response="200",
     *        description="Validation success",
     *        @OA\JsonContent(
     *           @OA\Property(property="message", type="string", example="A 6-digit code has been sent to your email"),
     *        )
     *     ),
     *
     *    @OA\Response(
     *        response="500",
     *        description="Validation success",
     *        @OA\JsonContent(
     *           @OA\Property(property="message", type="string", example="An error occurred! Please, try again."),
     *        )
     *     ),
     *
     *    @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *            @OA\Property(property="message", type="string", example="The given data was invalid."),
     *            @OA\Property(
     *               property="errors",
     *               type="object",
     *               @OA\Property(
     *                  property="email",
     *                  type="array",
     *                  collectionFormat="multi",
     *                  @OA\Items(
     *                     type="string",
     *                     example={"The email is already taken.","The email is invalid."},
     *                  )
     *               )
     *            )
     *         )
     *      )
     * )
     *
     * @param Request $request
     *
     * @return Response
     * @throws Exception
     */
    public function validateEditEmail(Request $request): Response
    {
        $this->validate($request, [
            'email' => [
                'required',
                'email',
                "unique:users,email",
            ],
        ]);

        try {
            $verificationCode = Str::random(6);
            $user = User::first(Auth::user()->id);
            $user->verification_code = Hash::make($verificationCode);

            if (!$user->save()) {
                throw new Exception();
            }

            // Should send SMS to the user's new email contaiing the verification code
            $response = Http::post('[COMMUNICATIONS_MS_URL]/messages/email/send-message', [
                'to' => $request->email,
                'message' => 'Your verification code is: ' . $verificationCode,
            ]);

            if (!$response->ok()) {
                throw new Exception();
            }

            return response()->jsonApi(["message" => "A 6-digit code has been sent to your email"], 200);
        } catch (Exception $e) {
            return response()->jsonApi(["message" => "An error occurred! Please, try again."], 500);
        }
    }

    /**
     * Get user object
     *
     * @param $id
     * @return mixed
     */
    private function getObject($id): mixed
    {
        try {
            return User::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->jsonApi([
                'type' => 'danger',
                'title' => "Get user",
                'message' => "User with id #{$id} not found: {$e->getMessage()}",
                'data' => ''
            ], 404);
        }
    }
}