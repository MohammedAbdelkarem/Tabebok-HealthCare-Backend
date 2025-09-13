<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Custom Status Codes
    |--------------------------------------------------------------------------
    | Values are the Custom Status Code used in my car application.
    | SC => Status Code.
    |
    */

    "honeybot_SC"           => 203,
    "complete_profile_SC"   => 220,

    /*
    |--------------------------------------------------------------------------
    | Default User Profile Image
    |--------------------------------------------------------------------------
    | This value is the default user profile image of your application.
    |
    */

    "user_default_image"    => "storage/assets/defaults/default_user.jpg",

    /*
    |--------------------------------------------------------------------------
    | Private File Storage Path
    |--------------------------------------------------------------------------
    | This value is used to define the folder where files should be stored as private files.
    | Notice that changing the path will not effect files already stored
    | in the system.
    |
    */

    "private_path" => "private/",

    /*
    |--------------------------------------------------------------------------
    | Loging Attempts Rate Limiter
    |--------------------------------------------------------------------------
    | max_attempts => Set the maximum number of login attempts
    | decay_minutes => Set the number of minutes until the login attempts reset
    |
    */

    "max_login_attempts"          => 5,
    "decay_login_minutes"         => 60 * 5,  // five minutes
    "admin_max_login_attempts"    => 5,
    "admin_decay_login_minutes"   => 60 * 15, // 15 minutes

    /*
    |--------------------------------------------------------------------------
    | Time to wait before generate new phone OTP
    |--------------------------------------------------------------------------
    | This value is used to define the needed seconds to wait before generate a new OTP.
    |
    */

    "time_to_new_OTP" => 60, //TODO:: Set to 60

    /*
    |--------------------------------------------------------------------------
    | OTP Expire Time
    |--------------------------------------------------------------------------
    | This value is used to define the whern otp in minutes.
    | Editing this will require to edit emails also
    |
    */

    "otp_expire_in" => 60, //Minutes

    /*
    |--------------------------------------------------------------------------
    | Accepted Languages
    |--------------------------------------------------------------------------
    | This value is used to define accepted user languages.
    |
    */

    "accepted_languages" => ['en', 'ar'],

    /*
    |--------------------------------------------------------------------------
    | Max ContactUs Links
    |--------------------------------------------------------------------------
    | This value is used to define max contact us links.
    |
    */

    "max_contact_us_links" => 15,

    /*
    |--------------------------------------------------------------------------
    | Application Emails
    |--------------------------------------------------------------------------
    | All Emails needed to let this app work.
    |
    */

    "support_email" => "support@email.com", //TODO Replace with real email

    /*
    |--------------------------------------------------------------------------
    | User Time Zone Offset
    |--------------------------------------------------------------------------
    | This value is used to define the offset of the time zone in user device.
    | Default 0
    */

    "user_time_zone" => 0,

    /*
    |--------------------------------------------------------------------------
    | Customer Service Card //TODO
    |--------------------------------------------------------------------------
    | This values is used to define Customer Service Card system variables.
    */

    "time_between_store_service_cards"      => 0,      //Minutes
    // "time_between_store_service_cards"      => 60,      //Minutes
    "time_between_two_serveic_messages"     => 10,  //Seconds 60 * 5
    // "time_between_two_serveic_messages"     => 60 * 5,  //Seconds 60 * 5
    "max_media_per_customer_card"           => 3,


    /*
    |--------------------------------------------------------------------------
    | Per Page default value
    |--------------------------------------------------------------------------
    | This values is used to define the deafult per_page parameter in requests
    |
    */

    'per_page_default_value' => 16,
    'max_per_page_value' => 100,

    /*
    |--------------------------------------------------------------------------
    | OTP Attempts Rate Limiter
    |--------------------------------------------------------------------------
    | otp_max_attempts  => Set the maximum number of otp attempts
    | otp_decay_minutes => Set the number of minutes until the login attempts reset
    |
    */

    "otp_max_attempts"          => 8,
    "otp_decay_minutes"         => 60 * 5,  // five minutes

    /*
    |--------------------------------------------------------------------------
    | Users Accounts Per Number
    |--------------------------------------------------------------------------
    |
    | Number of times user can use the same phone number to create account after
    | deleting the old account.
    |
    */

    "max_accounts_per_phone_number"          => 1,

    /*
    |--------------------------------------------------------------------------
    | Product Config
    |--------------------------------------------------------------------------
    | max_fields_per_subcategory  => Set the maximum number media files per
    | product.
    | max_product_video_size      => Max video size for product
    |
    */

    "max_media_per_product"         => 10,
    "max_product_video_size"        => 10240, // 10MB

    /*
    |--------------------------------------------------------------------------
    | Saved Search
    |--------------------------------------------------------------------------
    | max_saved_search_per_user  => Set the maximum amount of rows
    | that could be created by each user
    |
    */

    "max_saved_search_per_user"    => 30,
];
