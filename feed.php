<?php
// Include config file
include 'database/db_connect.php';

// Initialize the session
session_start();
 
// Check if the user is logged in, if not then redirect him to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: form-login.php");
    exit;
}
// Retrieve the user ID from the session
$userID = isset($_SESSION['UserID']) ? $_SESSION['UserID'] : null;

if (is_null($userID)) {
    echo "User ID is not set in the session.";
    exit;
}

// Query the database for the user's information
$sql = "SELECT firstname, lastname, email, photo FROM user WHERE UserID = ?";
$stmt = mysqli_prepare($conn, $sql);

// Bind parameters and execute the query
mysqli_stmt_bind_param($stmt, "i", $userID);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Check if user info is found
if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);

    // Store user information in variables
    $fname = $row['firstname'];
    $lname = $row['lastname'];
    $email = $row['email'];
    $photo = $row['photo'];
} else {
    echo "No user information found.";
    exit;
}

// Query to fetch posts with their comments
$sql = "SELECT 
            posts.PostID, posts.PostContent, posts.PostImage, posts.PostTime, user.username AS post_username, user.photo AS photo,
            (SELECT COUNT(*) FROM likes WHERE likes.PostID = posts.PostID) AS TotalLikes,
            comments.CommentID, comments.CommentContent, comments.CommentTime, users_comments.username AS comment_username, users_comments.photo AS comment_photo
        FROM posts
        JOIN user ON posts.UserID = user.UserID
        LEFT JOIN comments ON posts.PostID = comments.PostID
        LEFT JOIN user AS users_comments ON comments.UserID = users_comments.UserID
        ORDER BY posts.PostTime DESC, comments.CommentTime ASC";

$result = mysqli_query($conn, $sql);


// Organize data into an associative array
$posts = [];
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $postID = $row['PostID'];
        $_SESSION['PostID'] = $postID;

        // Initialize the post if it doesn't exist
        if (!isset($posts[$postID])) {
            $posts[$postID] = [
                'PostID' => $postID,
                'PostContent' => $row['PostContent'],
                'PostImage' => $row['PostImage'],
                'PostTime' => $row['PostTime'],
                'Username' => $row['post_username'],
                'Photo' => $row['photo'],
                'TotalLikes' => $row['TotalLikes'], // Add Total Likes
                'Comments' => []
            ];
        }

        

        // Add comments if they exist
        if (!empty($row['CommentID'])) {
            $posts[$postID]['Comments'][] = [
                'CommentID' => $row['CommentID'],
                'CommentContent' => $row['CommentContent'],
                'CommentTime' => $row['CommentTime'],
                'Username' => $row['comment_username'],
                'Photo' => $row['comment_photo']
            ];
        }
    }
}


//LIKES ON EACH POST
// Fetch posts from the database
// $sql = "SELECT * FROM posts ORDER BY PostTime DESC";
// $result = mysqli_query($conn, $sql);

// if (mysqli_num_rows($result) > 0) {
//     while ($post = mysqli_fetch_assoc($result)) {
//         $postID = $post['PostID'];

//         // Get total likes for each post
//         $likeSql = "SELECT COUNT(*) AS totalLikes FROM likes WHERE PostID = $postID";
//         $likeResult = mysqli_query($conn, $likeSql);
//         $likeRow = mysqli_fetch_assoc($likeResult);
//         $totalLikes = $likeRow['totalLikes'] ?? 0;

       
//         // echo "<button onclick='likePost($postID)'>Like</button>";
//         // echo "<span id='like-count-$postID'>$totalLikes</span> likes";
//         // echo "</div><br>";
//     }
// } else {
//     echo "No posts available.";
// }



// Check if Post ID exists in the session
$totalComments = 0;

// Check if PostID is set in the session
if (isset($_SESSION['PostID'])) {
    $postID = 6;
    
    try {
        // Query to count total comments for the specified post
        $sql = "
            SELECT COUNT(comments.CommentID) AS TotalComments
            FROM comments
            INNER JOIN posts ON comments.PostID = posts.PostID
            WHERE posts.PostID = ?
        ";
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            throw new Exception("Failed to prepare the statement: " . $conn->error);
        }

        // Bind parameters and execute the query
        $stmt->bind_param("i", $postID);
        $stmt->execute();
        $result = $stmt->get_result();

        // Fetch the total comments
        if ($result && $row = $result->fetch_assoc()) {
            $totalComments = $row['TotalComments'];
        }

        // Close the statement and connection
        $stmt->close();
    
    } catch (Exception $e) {
        $errorMessage = "Error: " . $e->getMessage();
    }
} else {
    $errorMessage = "Post ID not found in session.";
}




function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp); // Convert timestamp to UNIX time
    $current_time = time(); // Current UNIX time
    $time_difference = $current_time - $time_ago; // Difference in seconds
    $seconds = $time_difference;

    if ($seconds <= 60) {
        return "just now";
    } elseif ($seconds <= 3600) {
        $minutes = round($seconds / 60);
        return $minutes == 1 ? "a minute ago" : "$minutes minutes ago";
    } elseif ($seconds <= 86400) {
        $hours = round($seconds / 3600);
        return $hours == 1 ? "an hour ago" : "$hours hours ago";
    } elseif ($seconds <= 604800) {
        $days = round($seconds / 86400);
        return $days == 1 ? "yesterday" : "$days days ago";
    } elseif ($seconds <= 2419200) {
        $weeks = round($seconds / 604800);
        return $weeks == 1 ? "a week ago" : "$weeks weeks ago";
    } else {
        $months = round($seconds / 2419200);
        return $months == 1 ? "a month ago" : "$months months ago";
    }
}

// Close the connection
mysqli_close($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Favicon -->
    <link href="assets/images/favicon.png" rel="icon" type="image/png">

    <!-- title and description-->
    <title>Socialite</title>
    <meta name="description" content="Socialite - Social sharing network HTML Template">
   
    <!-- css files -->
    <link rel="stylesheet" href="assets/css/tailwind.css">
    <link rel="stylesheet" href="assets/css/style.css">  
    
    <!-- google font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
 
</head>
<body>
 
    <div id="wrapper">

        <!-- header -->
        <header class="z-[100] h-[--m-top] fixed top-0 left-0 w-full flex items-center bg-white/80 sky-50 backdrop-blur-xl border-b border-slate-200 dark:bg-dark2 dark:border-slate-800">

            <div class="flex items-center w-full xl:px-6 px-2 max-lg:gap-10">

                <div class="2xl:w-[--w-side] lg:w-[--w-side-sm]">

                    <!-- left -->
                    <div class="flex items-center gap-1"> 

                        <!-- icon menu -->
                        <button uk-toggle="target: #site__sidebar ; cls :!-translate-x-0"
                                class="flex items-center justify-center w-8 h-8 text-xl rounded-full hover:bg-gray-100 xl:hidden dark:hover:bg-slate-600 group"> 
                                <ion-icon name="menu-outline" class="text-2xl group-aria-expanded:hidden"></ion-icon>
                                <ion-icon name="close-outline" class="hidden text-2xl group-aria-expanded:block"></ion-icon>
                        </button>
                        <div id="logo">
                            <a href="feed.php"> 
                                <img src="assets/images/logo.png" alt="" class="w-28 md:block hidden dark:!hidden">
                                <img src="assets/images/logo.png" alt="" class="dark:md:block hidden">
                                <img src="assets/images/logo.png" class="hidden max-md:block w-20 dark:!hidden" alt="">
                                <img src="assets/images/logo.png" class="hidden dark:max-md:block w-20" alt="">
                            </a>
                        </div>
                         
                    </div>

                </div>
                <div class="flex-1 relative">

                    <div class="max-w-[1220px] mx-auto flex items-center">

                        <!-- header icons -->
                        <div class="flex items-center sm:gap-4 gap-2 absolute right-5 top-1/2 -translate-y-1/2 text-black">
        
                            <!-- profile -->
                            <div  class="rounded-full relative bg-secondery cursor-pointer shrink-0">
                                <img src="<?php echo !empty($photo) ? htmlspecialchars($photo) : 'assets/images/avatars/avatar-2.jpg'; ?>" alt="" class="sm:w-9 sm:h-9 w-7 h-7 rounded-full shadow shrink-0"> 
                            </div>
                            <div  class="hidden bg-white rounded-lg drop-shadow-xl dark:bg-slate-700 w-64 border2"
                                uk-drop="offset:6;pos: bottom-right;animate-out: true; animation: uk-animation-scale-up uk-transform-origin-top-right ">
                                <a href="timeline.html">
                                    <div class="p-4 py-5 flex items-center gap-4">
                                        <img src="<?php echo !empty($photo) ? htmlspecialchars($photo) : 'assets/images/avatars/avatar-2.jpg'; ?>" alt="" class="w-10 h-10 rounded-full shadow">
                                        <div class="flex-1">
                                            <h4 class="text-sm font-medium text-black">
                                            <?php echo htmlspecialchars($fname)." ".htmlspecialchars($lname); ?>
                                            </h4>
                                            <div class="text-sm mt-1 text-blue-600 font-light dark:text-white/70">
                                            <?php echo htmlspecialchars($email); ?> 
                                            </div>
                                        </div>
                                    </div>
                                </a>
                                <hr class="dark:border-gray-600/60">
                                <nav class="p-2 text-sm text-black font-normal dark:text-white">
                                    <a href="setting.php">
                                        <div class="flex items-center gap-2.5 hover:bg-secondery p-2 px-2.5 rounded-md dark:hover:bg-white/10"> 
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                            My Account
                                        </div>
                                    </a>
                                    <hr class="-mx-2 my-2 dark:border-gray-600/60">
                                    <a href="logout.php">
                                        <div class="flex items-center gap-2.5 hover:bg-secondery p-2 px-2.5 rounded-md dark:hover:bg-white/10"> 
                                            <svg class="w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                            </svg>
                                            Log Out 
                                        </div>
                                    </a>
                                </nav>
                            </div> 

                            <div class="flex items-center gap-2 hidden">
                                <img src="assets/images/avatars/avatar-2.jpg" alt="" class="w-9 h-9 rounded-full shadow">
        
                                <div class="w-20 font-semibold text-gray-600"> Hamse </div>
        
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg> 
        
                            </div> 
        
                        </div>

                    </div> 

                </div>

            </div>

        </header>
    
        <!-- sidebar -->
        <div id="site__sidebar" class="fixed top-0 left-0 z-[99] pt-[--m-top] overflow-hidden transition-transform xl:duration-500 max-xl:w-full max-xl:-translate-x-full">

            <!-- sidebar inner -->
            <div class="p-2 max-xl:bg-white shadow-sm 2xl:w-72 sm:w-64 w-[80%] h-[calc(100vh-64px)] relative z-30 max-lg:border-r dark:max-xl:!bg-slate-700 dark:border-slate-700">
        
                <div class="pr-4" data-simplebar>

                    <nav id="side">
                    
                        <ul>
                            <li class="active">
                                <a href="feed.html">
                                    <img src="assets/images/icons/home.png" alt="feeds" class="w-6">
                                    <span> Feed </span> 
                                </a>
                            </li>
                    </ul>
                    </nav>

                </div>

            </div>

            <!-- sidebar overly -->
            <div id="site__sidebar__overly" 
                class="absolute top-0 left-0 z-20 w-screen h-screen xl:hidden backdrop-blur-sm"
                uk-toggle="target: #site__sidebar ; cls :!-translate-x-0"> 
            </div>
        </div>

        <!-- main contents -->
        <main id="site__main" class="2xl:ml-[--w-side]  xl:ml-[--w-side-sm] p-2.5 h-[calc(100vh-var(--m-top))] mt-[--m-top]">

            <!-- timeline -->
            <div class="lg:flex 2xl:gap-16 gap-12 max-w-[1065px] mx-auto"  id="js-oversized">

                <div class="max-w-[680px] mx-auto">

                    <!-- stories -->
                    <div class="mb-8">

                        <h3 class="font-extrabold text-2xl  text-black dark:text-white hidden"> Stories</h3>

                        <div class="relative" tabindex="-1" uk-slider="auto play: true;finite: true" uk-lightbox="">
        
                            <div class="py-5 uk-slider-container">
                            
                                <ul class="uk-slider-items w-[calc(100%+14px)]" uk-scrollspy="target: > li; cls: uk-animation-scale-up; delay: 20;repeat:true">
                                    <li class="md:pr-3" uk-scrollspy-class="uk-animation-fade">
                                        <div class="md:w-16 md:h-16 w-12 h-12 rounded-full relative border-2 border-dashed grid place-items-center bg-slate-200 border-slate-300 dark:border-slate-700 dark:bg-dark2 shrink-0"
                                             uk-toggle="target: #create-story">
                                            <ion-icon name="camera" class="text-2xl"></ion-icon>
                                        </div>
                                    </li>
                                    <li class="md:pr-3 pr-2 hover:scale-[1.15] hover:-rotate-2 duration-300">
                                        <a href="assets/images/avatars/avatar-lg-1.jpg" data-caption="Caption 1">
                                            <div class="md:w-16 md:h-16 w-12 h-12 relative md:border-4 border-2 shadow border-white rounded-full overflow-hidden dark:border-slate-700">
                                                <img src="assets/images/avatars/avatar-2.jpg" alt="" class="absolute w-full h-full object-cover">
                                            </div>
                                        </a>
                                    </li>
                                    <li class="md:pr-3 pr-2 hover:scale-[1.15] hover:-rotate-2 duration-300">
                                        <a href="assets/images/avatars/avatar-lg-2.jpg" data-caption="Caption 1">
                                            <div class="md:w-16 md:h-16 w-12 h-12 relative md:border-4 border-2 shadow border-white rounded-full overflow-hidden dark:border-slate-700">
                                                <img src="assets/images/avatars/avatar-3.jpg" alt="" class="absolute w-full h-full object-cover">
                                            </div>
                                        </a>
                                    </li> 
                                    <li class="md:pr-3 pr-2 hover:scale-[1.15] hover:-rotate-2 duration-300">
                                        <a href="assets/images/avatars/avatar-lg-4.jpg" data-caption="Caption 1">
                                            <div class="md:w-16 md:h-16 w-12 h-12 relative md:border-4 border-2 shadow border-white rounded-full overflow-hidden dark:border-slate-700">
                                                <img src="assets/images/avatars/avatar-5.jpg" alt="" class="absolute w-full h-full object-cover">
                                            </div>
                                        </a>
                                    </li>
                                    <li class="md:pr-3 pr-2 hover:scale-[1.15] hover:-rotate-2 duration-300">
                                        <a href="assets/images/avatars/avatar-lg-5.jpg" data-caption="Caption 1">
                                            <div class="md:w-16 md:h-16 w-12 h-12 relative md:border-4 border-2 shadow border-white rounded-full overflow-hidden dark:border-slate-700">
                                                <img src="assets/images/avatars/avatar-6.jpg" alt="" class="absolute w-full h-full object-cover">
                                            </div>
                                        </a>
                                    </li>
                                    <li class="md:pr-3 pr-2 hover:scale-[1.15] hover:-rotate-2 duration-300">
                                        <a href="assets/images/avatars/avatar-lg-1.jpg" data-caption="Caption 1">
                                            <div class="md:w-16 md:h-16 w-12 h-12 relative md:border-4 border-2 shadow border-white rounded-full overflow-hidden dark:border-slate-700">
                                                <img src="assets/images/avatars/avatar-7.jpg" alt="" class="absolute w-full h-full object-cover">
                                            </div>
                                        </a>
                                    </li>
                                    <li class="md:pr-3 pr-2 hover:scale-[1.15] hover:-rotate-2 duration-300">
                                        <a href="assets/images/avatars/avatar-lg-1.jpg" data-caption="Caption 1">
                                            <div class="md:w-16 md:h-16 w-12 h-12 relative md:border-4 border-2 shadow border-white rounded-full overflow-hidden dark:border-slate-700">
                                                <img src="assets/images/avatars/avatar-2.jpg" alt="" class="absolute w-full h-full object-cover">
                                            </div>
                                        </a>
                                    </li>
                                    <li class="md:pr-3 pr-2 hover:scale-[1.15] hover:-rotate-2 duration-300">
                                        <a href="assets/images/avatars/avatar-lg-2.jpg" data-caption="Caption 1">
                                            <div class="md:w-16 md:h-16 w-12 h-12 relative md:border-4 border-2 shadow border-white rounded-full overflow-hidden dark:border-slate-700">
                                                <img src="assets/images/avatars/avatar-3.jpg" alt="" class="absolute w-full h-full object-cover">
                                            </div>
                                        </a>
                                    </li> 
                                    <li class="md:pr-3 pr-2 hover:scale-[1.15] hover:-rotate-2 duration-300">
                                        <a href="assets/images/avatars/avatar-lg-4.jpg" data-caption="Caption 1">
                                            <div class="md:w-16 md:h-16 w-12 h-12 relative md:border-4 border-2 shadow border-white rounded-full overflow-hidden dark:border-slate-700">
                                                <img src="assets/images/avatars/avatar-5.jpg" alt="" class="absolute w-full h-full object-cover">
                                            </div>
                                        </a>
                                    </li>
                                    <li class="md:pr-3 pr-2 hover:scale-[1.15] hover:-rotate-2 duration-300">
                                        <a href="assets/images/avatars/avatar-lg-5.jpg" data-caption="Caption 1">
                                            <div class="md:w-16 md:h-16 w-12 h-12 relative md:border-4 border-2 shadow border-white rounded-full overflow-hidden dark:border-slate-700">
                                                <img src="assets/images/avatars/avatar-6.jpg" alt="" class="absolute w-full h-full object-cover">
                                            </div>
                                        </a>
                                    </li>
                                    <li class="md:pr-3 pr-2 hover:scale-[1.15] hover:-rotate-2 duration-300">
                                        <a href="assets/images/avatars/avatar-lg-1.jpg" data-caption="Caption 1">
                                            <div class="md:w-16 md:h-16 w-12 h-12 relative md:border-4 border-2 shadow border-white rounded-full overflow-hidden dark:border-slate-700">
                                                <img src="assets/images/avatars/avatar-7.jpg" alt="" class="absolute w-full h-full object-cover">
                                            </div>
                                        </a>
                                    </li>
                                    <li class="md:pr-3 pr-2">
                                        <div class="md:w-16 md:h-16 w-12 h-12 bg-slate-200/60 rounded-full dark:bg-dark2 animate-pulse"></div>
                                    </li>
                                </ul>
                        
                            </div>
                        
                            <div class="max-md:hidden">
                                <button type="button" class="absolute -translate-y-1/2 bg-white shadow rounded-full top-1/2 -left-3.5 grid w-8 h-8 place-items-center dark:bg-dark3" uk-slider-item="previous"> <ion-icon name="chevron-back" class="text-2xl"></ion-icon></button>
                                <button type="button" class="absolute -right-2 -translate-y-1/2 bg-white shadow rounded-full top-1/2 grid w-8 h-8 place-items-center dark:bg-dark3" uk-slider-item="next"> <ion-icon name="chevron-forward" class="text-2xl"></ion-icon> </button>
                            </div>
                        </div>

                    </div>

                    <!-- feed story -->
                    <div class="md:max-w-[580px] mx-auto flex-1 xl:space-y-6 space-y-3">

                        <!-- add story -->
                        <div class="bg-white rounded-xl shadow-sm md:p-4 p-2 space-y-4 text-sm font-medium border1 dark:bg-dark2">

                            <div class="flex items-center md:gap-3 gap-1">
                                <div class="flex-1 bg-slate-100 hover:bg-opacity-80 transition-all rounded-lg cursor-pointer dark:bg-dark3" uk-toggle="target: #create-status"> 
                                    <div class="py-2.5 text-center dark:text-white"> What do you have in mind? </div>
                                </div>
                                <div class="cursor-pointer hover:bg-opacity-80 p-1 px-1.5 rounded-xl transition-all bg-pink-100/60 hover:bg-pink-100 dark:bg-white/10 dark:hover:bg-white/20" uk-toggle="target: #create-status">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 stroke-pink-600 fill-pink-200/70" viewBox="0 0 24 24" stroke-width="1.5" stroke="#2c3e50" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M15 8h.01" />
                                        <path d="M12 3c7.2 0 9 1.8 9 9s-1.8 9 -9 9s-9 -1.8 -9 -9s1.8 -9 9 -9z" />
                                        <path d="M3.5 15.5l4.5 -4.5c.928 -.893 2.072 -.893 3 0l5 5" />
                                        <path d="M14 14l1 -1c.928 -.893 2.072 -.893 3 0l2.5 2.5" />
                                    </svg>
                                </div>
                                <div class="cursor-pointer hover:bg-opacity-80 p-1 px-1.5 rounded-xl transition-all bg-sky-100/60 hover:bg-sky-100 dark:bg-white/10 dark:hover:bg-white/20" uk-toggle="target: #create-status">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 stroke-sky-600 fill-sky-200/70 " viewBox="0 0 24 24" stroke-width="1.5" stroke="#2c3e50" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <path d="M15 10l4.553 -2.276a1 1 0 0 1 1.447 .894v6.764a1 1 0 0 1 -1.447 .894l-4.553 -2.276v-4z" />
                                        <path d="M3 6m0 2a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v8a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2z" />
                                    </svg>
                                </div> 
                            </div>
                            
                        </div>
                        

                        
                        <!--  post image-->
                        <?php if (!empty($posts)): ?>
                            <?php foreach ($posts as $post): ?>
                        <div class="bg-white rounded-xl shadow-sm text-sm font-medium border1 dark:bg-dark2">

                            <!-- post heading -->
                            <div class="flex gap-3 sm:p-4 p-2.5 text-sm font-medium"> 
                                <a href="timeline.html"> <img src="<?php echo !empty($post['Photo']) ? htmlspecialchars($post['Photo']) : 'assets/images/avatars/avatar-2.jpg'; ?>" alt="" class="w-9 h-9 rounded-full"> </a>  
                                <div class="flex-1">
                                    <a href="timeline.html"> <h4 class="text-black dark:text-white">  <?php echo htmlspecialchars($post['Username']); ?> </h4> </a>  
                                    <div class="text-xs text-gray-500 dark:text-white/80">  <?php echo timeAgo($post['PostTime']); ?></div>
                                </div>

                                <div class="-mr-1">
                                    <button type="button" class="button-icon w-8 h-8"> <ion-icon class="text-xl" name="ellipsis-horizontal"></ion-icon> </button>
                                    <div  class="w-[245px]" uk-dropdown="pos: bottom-right; animation: uk-animation-scale-up uk-transform-origin-top-right; animate-out: true; mode: click"> 
                                        <nav> 
                                            <a href="#"> <ion-icon class="text-xl shrink-0" name="bookmark-outline"></ion-icon>  Add to favorites </a>  
                                            <a href="#"> <ion-icon class="text-xl shrink-0" name="notifications-off-outline"></ion-icon> Mute Notification </a>  
                                            <a href="#"> <ion-icon class="text-xl shrink-0" name="flag-outline"></ion-icon>  Report this post </a>  
                                            <a href="#"> <ion-icon class="text-xl shrink-0" name="share-outline"></ion-icon>  Share your profile </a>  
                                            <hr>
                                            <a href="#" class="text-red-400 hover:!bg-red-50 dark:hover:!bg-red-500/50"> <ion-icon class="text-xl shrink-0" name="stop-circle-outline"></ion-icon>  Unfollow </a>  
                                        </nav>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- post image -->
                            <?php if (!empty($post['PostImage'])): ?>
                            <a href="#preview_modal" uk-toggle>
                                <div class="relative w-full lg:h-96 h-full sm:px-4">
                                   <img src="uploads/posts/<?php echo htmlspecialchars($post['PostImage']); ?>" alt='' class='sm:rounded-lg w-full h-full object-cover'>";
                                </div>
                            </a>
                            <?php endif; ?>

                            <div class="sm:px-4 p-2.5 pt-0">
                                <p class="font-normal"><?php echo htmlspecialchars($post['PostContent']); ?></p>
                            </div>
                            <!-- post icons -->
                            <div class="sm:p-4 p-2.5 flex items-center gap-4 text-xs font-semibold">
                                <div>
                                    <div class="flex items-center gap-2.5">
                                        <button type="button"  class="text-red-600 hover:scale-125 duration-300"> <ion-icon class="text-lg" name="heart"></ion-icon> </button>
                                        <a href="#" id="like-count-<?= $post['PostID'] ?>"><?= $post['TotalLikes'] ?></a>
                                    </div>
                                    <div    class="p-1 px-2 bg-white rounded-full drop-shadow-md w-[212px] dark:bg-slate-700 text-2xl"
                                            uk-drop="offset:10;pos: top-left; animate-out: true; animation: uk-animation-scale-up uk-transform-origin-bottom-left"> 
                                        
                                        <div class="flex gap-2"  uk-scrollspy="target: > button; cls: uk-animation-scale-up; delay: 100 ;repeat: true">
                                            <button type="button"  class="text-red-600 hover:scale-125 duration-300" onclick="likePost(<?= $post['PostID'] ?>)"> <span> 👍 </span></button>
                                            <button type="button" class="text-red-600 hover:scale-125 duration-300"onclick="likePost(<?= $post['PostID'] ?>)"> <span> ❤️ </span></button>
                                            <button type="button" class="text-red-600 hover:scale-125 duration-300" onclick="likePost(<?= $post['PostID'] ?>)"> <span> 😂 </span></button>
                                            <button type="button" class="text-red-600 hover:scale-125 duration-300" onclick="likePost(<?= $post['PostID'] ?>)"> <span> 😯 </span></button>
                                            <button type="button" class="text-red-600 hover:scale-125 duration-300" onclick="likePost(<?= $post['PostID'] ?>)"> <span> 😢 </span></button>
                                        </div>
                                        
                                        <div class="w-2.5 h-2.5 absolute -bottom-1 left-3 bg-white rotate-45 hidden"></div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <button type="button" class="button-icon bg-slate-200/70 dark:bg-slate-700"> <ion-icon class="text-lg" name="chatbubble-ellipses"></ion-icon> </button>
                                   
                                        
                                    <span><?php echo htmlspecialchars($totalComments); ?></span>
                                    
                                    
                                </div>
                                <button type="button" class="button-icon ml-auto"> <ion-icon class="text-xl" name="paper-plane-outline"></ion-icon> </button>
                                <button type="button" class="button-icon"> <ion-icon class="text-xl" name="share-outline"></ion-icon> </button>
                            </div>

                            <!-- comments -->
                            <div class="sm:p-4 p-2.5 border-t border-gray-100 font-normal space-y-3 relative dark:border-slate-700/40"> 
                            <?php if (!empty($post['Comments'])): ?>
                                <?php foreach ($post['Comments'] as $comment): ?>
                                <div class="flex items-start gap-3 relative">
                                    <a href="timeline.html"> <img src="<?php echo !empty($comment['Photo']) ? htmlspecialchars($comment['Photo']) : 'assets/images/avatars/avatar-2.jpg'; ?>" alt="" class="w-6 h-6 mt-1 rounded-full"> </a>
                                    <div class="flex-1">
                                        <a href="timeline.html" class="text-black font-medium inline-block dark:text-white"><?php echo htmlspecialchars($comment['Username']); ?> </a>
                                        <p class="mt-0.5"><?php echo htmlspecialchars($comment['CommentContent']); ?></p>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php else: ?>
                                    <p>No comments yet.</p>
                                <?php endif; ?>
                                <button type="button" class="flex items-center gap-1.5 text-gray-500 hover:text-blue-500 mt-2">
                                    <ion-icon name="chevron-down-outline" class="ml-auto duration-200 group-aria-expanded:rotate-180"></ion-icon>
                                    More Comment
                                </button>

                            </div>

                            <!-- add comment -->
                            <form method="POST" action="comment.php">
                            <input type="hidden" name="PostID" value="<?php echo htmlspecialchars($post['PostID']); ?>">
                            <div class="sm:px-4 sm:py-3 p-2.5 border-t border-gray-100 flex items-center gap-1 dark:border-slate-700/40">
                                
                                    <img src="<?php echo !empty($post['Photo']) ? htmlspecialchars($post['Photo']) : 'assets/images/avatars/avatar-2.jpg'; ?>" alt="" class="w-6 h-6 rounded-full">
                                    
                                    <div class="flex-1 relative overflow-hidden h-10">
                                    <textarea name="CommentContent" placeholder="Add Comment...." rows="1" class="w-full resize-  px-4 py-2 focus:!border-transparent focus:!ring-transparent resize-y"></textarea>
                                        <!-- <textarea name="CommentContent" placeholder="Add Comment...." rows="1" class="w-full resize-none !bg-transparent px-4 py-2 focus:!border-transparent focus:!ring-transparent"></textarea> -->

                                        <div class="!top-2 pr-2" uk-drop="pos: bottom-right; mode: click">
                                            <div class="flex items-center gap-2" uk-scrollspy="target: > svg; cls: uk-animation-slide-right-small; delay: 100 ;repeat: true">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-6 h-6 fill-sky-600">
                                                    <path fill-rule="evenodd" d="M1.5 6a2.25 2.25 0 012.25-2.25h16.5A2.25 2.25 0 0122.5 6v12a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 011.5 18V6zM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0021 18v-1.94l-2.69-2.689a1.5 1.5 0 00-2.12 0l-.88.879.97.97a.75.75 0 11-1.06 1.06l-5.16-5.159a1.5 1.5 0 00-2.12 0L3 16.061zm10.125-7.81a1.125 1.125 0 112.25 0 1.125 1.125 0 01-2.25 0z" clip-rule="evenodd" />
                                                </svg>
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 fill-pink-600">
                                                    <path d="M3.25 4A2.25 2.25 0 001 6.25v7.5A2.25 2.25 0 003.25 16h7.5A2.25 2.25 0 0013 13.75v-7.5A2.25 2.25 0 0010.75 4h-7.5zM19 4.75a.75.75 0 00-1.28-.53l-3 3a.75.75 0 00-.22.53v4.5c0 .199.079.39.22.53l3 3a.75.75 0 001.28-.53V4.75z" />
                                                </svg>
                                            </div>
                                        </div>
                                        

                                    </div>
                                    

                                    <button type="submit" class="text-sm rounded-full py-1.5 px-3.5 bg-secondery"> Replay</button>

                            </div> 
                            </form>

                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No posts available.</p>
                    <?php endif; ?>

 
                        
    
                        <!--  post image with slider-->
                    

                        <!-- post text-->
                       

                        <!-- placeholder -->
                      

                    </div>

                </div>

                <!-- sidebar -->
                <div class="flex-1"> 
                    
                    <div class="lg:space-y-4 lg:pb-8 max-lg:grid sm:grid-cols-2 max-lg:gap-6" 
                    uk-sticky="media: 1024; end: #js-oversized; offset: 80">

                    <div class="box p-5 px-6">
        
                        <div class="flex items-baseline justify-between text-black dark:text-white">
                            <h3 class="font-bold text-base"> People you may know </h3>
                            <a href="#" class="text-sm text-blue-500">See all</a>
                        </div>

                        <div class="side-list">

                            <div class="side-list-item">
                                <a href="timeline.html">
                                    <img src="assets/images/avatars/avatar-2.jpg" alt="" class="side-list-image rounded-full">
                                </a>
                                <div class="flex-1">
                                    <a href="timeline.html"><h4 class="side-list-title">  John Michael </h4></a>
                                    <div class="side-list-info"> 125k Following </div>
                                </div>
                                <button class="button bg-primary-soft text-primary dark:text-white">follow</button>
                            </div>

                            <div class="side-list-item">
                                <a href="timeline.html">
                                    <img src="assets/images/avatars/avatar-3.jpg" alt="" class="side-list-image rounded-full">
                                </a>
                                <div class="flex-1">
                                    <a href="timeline.html"><h4 class="side-list-title"> Monroe Parker </h4></a>
                                    <div class="side-list-info"> 320k Following </div>
                                </div>
                                <button class="button bg-primary-soft text-primary dark:text-white">follow</button>
                            </div>  
                            
                            <div class="side-list-item">
                                <a href="timeline.html">
                                    <img src="assets/images/avatars/avatar-5.jpg" alt="" class="side-list-image rounded-full">
                                </a>
                                <div class="flex-1">
                                    <a href="timeline.html"><h4 class="side-list-title"> James Lewis</h4></a>
                                    <div class="side-list-info"> 125k Following </div>
                                </div>
                                <button class="button bg-primary-soft text-primary dark:text-white">follow</button>
                            </div>
                            
                            <div class="side-list-item">
                                <a href="timeline.html">
                                    <img src="assets/images/avatars/avatar-6.jpg" alt="" class="side-list-image rounded-full">
                                </a>
                                <div class="flex-1">
                                    <a href="timeline.html"><h4 class="side-list-title">  Alexa stella </h4></a>
                                    <div class="side-list-info"> 192k Following </div>
                                </div>
                                <button class="button bg-primary-soft text-primary dark:text-white">follow</button>
                            </div>

                            <div class="side-list-item">
                                <a href="timeline.html">
                                    <img src="assets/images/avatars/avatar-2.jpg" alt="" class="side-list-image rounded-full">
                                </a>
                                <div class="flex-1">
                                    <a href="timeline.html"><h4 class="side-list-title"> John Michael </h4></a>
                                    <div class="side-list-info"> 320k Following </div>
                                </div>
                                <button class="button bg-primary-soft text-primary dark:text-white">follow</button>
                            </div>  
                            
                            <button class="bg-secondery button w-full mt-2 hidden">See all</button>

                        </div>

                    </div>

                    <!-- peaple you might know -->
                    <div class="box p-5 px-6 border1 dark:bg-dark2 hidden">
                                    
                        <div class="flex justify-between text-black dark:text-white">
                            <h3 class="font-bold text-base"> Peaple You might know </h3>
                            <button type="button"> <ion-icon name="sync-outline" class="text-xl"></ion-icon> </button>
                        </div>

                        <div class="space-y-4 capitalize text-xs font-normal mt-5 mb-2 text-gray-500 dark:text-white/80">

                            <div class="flex items-center gap-3">
                                <a href="timeline.html">
                                    <img src="assets/images/avatars/avatar-7.jpg" alt="" class="bg-gray-200 rounded-full w-10 h-10">
                                </a>
                                <div class="flex-1">
                                    <a href="timeline.html"><h4 class="font-semibold text-sm text-black dark:text-white">  Johnson smith</h4></a>
                                    <div class="mt-0.5"> Suggested For You </div>
                                </div>
                                <button type="button" class="text-sm rounded-full py-1.5 px-4 font-semibold bg-secondery"> Follow </button>
                            </div>
                            <div class="flex items-center gap-3">
                                <a href="timeline.html">
                                    <img src="assets/images/avatars/avatar-5.jpg" alt="" class="bg-gray-200 rounded-full w-10 h-10">
                                </a>
                                <div class="flex-1">
                                    <a href="timeline.html"><h4 class="font-semibold text-sm text-black dark:text-white"> James Lewis</h4></a>
                                    <div class="mt-0.5"> Followed by Johnson </div>
                                </div>
                                <button type="button" class="text-sm rounded-full py-1.5 px-4 font-semibold bg-secondery"> Follow </button>
                            </div>
                            <div class="flex items-center gap-3">
                                <a href="timeline.html">
                                    <img src="assets/images/avatars/avatar-2.jpg" alt="" class="bg-gray-200 rounded-full w-10 h-10">
                                </a>
                                <div class="flex-1">
                                    <a href="timeline.html"><h4 class="font-semibold text-sm text-black dark:text-white"> John Michael</h4></a>
                                    <div class="mt-0.5"> Followed by Monroe  </div>
                                </div>
                                <button type="button" class="text-sm rounded-full py-1.5 px-4 font-semibold bg-secondery"> Follow </button>
                            </div>
                            <div class="flex items-center gap-3">
                                <a href="timeline.html">
                                    <img src="assets/images/avatars/avatar-3.jpg" alt="" class="bg-gray-200 rounded-full w-10 h-10">
                                </a>
                                <div class="flex-1">
                                    <a href="timeline.html"><h4 class="font-semibold text-sm text-black dark:text-white">  Monroe Parker</h4></a>
                                    <div class="mt-0.5"> Suggested For You </div>
                                </div>
                                <button type="button" class="text-sm rounded-full py-1.5 px-4 font-semibold bg-secondery"> Follow </button>
                            </div> 
                            <div class="flex items-center gap-3">
                                <a href="timeline.html">
                                    <img src="assets/images/avatars/avatar-4.jpg" alt="" class="bg-gray-200 rounded-full w-10 h-10">
                                </a>
                                <div class="flex-1">
                                    <a href="timeline.html"><h4 class="font-semibold text-sm text-black dark:text-white">  Martin Gray</h4></a>
                                    <div class="mt-0.5"> Suggested For You </div>
                                </div>
                                <button type="button" class="text-sm rounded-full py-1.5 px-4 font-semibold bg-secondery"> Follow </button>
                            </div>
                        </div>

                    </div>

                    <!-- Trends -->
                    <div class="box p-5 px-6 border1 dark:bg-dark2">
                        
                        <div class="flex justify-between text-black dark:text-white">
                            <h3 class="font-bold text-base"> Trends for you </h3>
                            <button type="button"> <ion-icon name="sync-outline" class="text-xl"></ion-icon> </button>
                        </div>

                        <div class="space-y-3.5 capitalize text-xs font-normal mt-5 mb-2 text-gray-600 dark:text-white/80">
                            <a href="#">
                                <div class="flex items-center gap-3 p"> 
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 -mt-2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5l-3.9 19.5m-2.1-19.5l-3.9 19.5" />
                                    </svg>
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-black dark:text-white text-sm">  artificial intelligence </h4>
                                        <div class="mt-0.5"> 1,245,62 post </div>
                                    </div> 
                                </div>
                            </a>
                            <a href="#" class="block">
                                <div class="flex items-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 -mt-2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5l-3.9 19.5m-2.1-19.5l-3.9 19.5" />
                                    </svg>
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-black dark:text-white text-sm">  Web developers</h4>
                                        <div class="mt-0.5"> 1,624 post </div>
                                    </div> 
                                </div>
                            </a>
                            <a href="#" class="block">
                                <div class="flex items-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 -mt-2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5l-3.9 19.5m-2.1-19.5l-3.9 19.5" />
                                    </svg>
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-black dark:text-white text-sm">  Ui Designers</h4>
                                        <div class="mt-0.5"> 820 post </div>
                                    </div> 
                                </div>
                            </a>
                            <a href="#" class="block">
                                <div class="flex items-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 -mt-2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5l-3.9 19.5m-2.1-19.5l-3.9 19.5" />
                                    </svg>
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-black dark:text-white text-sm"> affiliate marketing </h4>
                                        <div class="mt-0.5"> 480 post </div>
                                    </div> 
                                </div>
                            </a>
                        </div>

                        
                    </div>

                </div>
                </div>
                
            </div>
            
        </main>

    </div>

    



<!-- PREVIEW MODEL FOR POST -->


    <!-- post preview modal --> 
    <?php if (!empty($posts)): ?>
        <?php foreach ($posts as $post): ?> 
    <div class="hidden lg:p-20 max-lg:!items-start" id="preview_modal" uk-modal="">
        
        <div class="uk-modal-dialog tt relative mx-auto overflow-hidden shadow-xl rounded-lg lg:flex items-center ax-w-[86rem] w-full lg:h-[80vh]">
          
            <!-- image previewer -->
            <div class="lg:h-full lg:w-[calc(100vw-400px)] w-full h-96 flex justify-center items-center relative">
                
                <div class="relative z-10 w-full h-full">
                    <img src="uploads/posts/<?php echo htmlspecialchars($post['PostImage']); ?>" alt="" class="w-full h-full object-cover absolute">
                </div>
  
                <!-- close button -->
                <button type="button"  class="bg-white rounded-full p-2 absolute right-0 top-0 m-3 uk-animation-slide-right-medium z-10 dark:bg-slate-600 uk-modal-close">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

            </div>

            <!-- right sidebar -->
            <div class="lg:w-[400px] w-full bg-white h-full relative  overflow-y-auto shadow-xl dark:bg-dark2 flex flex-col justify-between">
                
                <div class="p-5 pb-0">

                    <!-- story heading -->
                    <div class="flex gap-3 text-sm font-medium">
                        <img src="assets/images/avatars/avatar-5.jpg" alt="" class="w-9 h-9 rounded-full">
                        <div class="flex-1">
                            <h4 class="text-black font-medium dark:text-white"> <?php echo htmlspecialchars($post['Username']); ?> </h4>
                            <div class="text-gray-500 text-xs dark:text-white/80"><?php echo timeAgo($post['PostTime']); ?></div>
                        </div>
 
                        <!-- dropdown -->
                        <div class="-m-1">
                            <button type="button" class="button__ico w-8 h-8"> <ion-icon class="text-xl" name="ellipsis-horizontal"></ion-icon> </button>
                            <div  class="w-[253px]" uk-dropdown="pos: bottom-right; animation: uk-animation-scale-up uk-transform-origin-top-right; animate-out: true"> 
                                <nav> 
                                    <a href="#"> <ion-icon class="text-xl shrink-0" name="bookmark-outline"></ion-icon>  Add to favorites </a>  
                                    <a href="#"> <ion-icon class="text-xl shrink-0" name="notifications-off-outline"></ion-icon> Mute Notification </a>  
                                    <a href="#"> <ion-icon class="text-xl shrink-0" name="flag-outline"></ion-icon>  Report this post </a>  
                                    <a href="#"> <ion-icon class="text-xl shrink-0" name="share-outline"></ion-icon>  Share your profile </a>  
                                    <hr>
                                    <a href="#" class="text-red-400 hover:!bg-red-50 dark:hover:!bg-red-500/50"> <ion-icon class="text-xl shrink-0" name="stop-circle-outline"></ion-icon>  Unfollow </a>  
                                </nav>
                            </div>
                        </div>
                    </div>

                    <p class="font-normal text-sm leading-6 mt-4"> <?php echo htmlspecialchars($post['PostContent']); ?></p>

                    <div class="shadow relative -mx-5 px-5 py-3 mt-3">
                        <div class="flex items-center gap-4 text-xs font-semibold">
                            <div class="flex items-center gap-2.5">
                                <button type="button" class="button__ico text-red-500 bg-red-100 dark:bg-slate-700"> <ion-icon class="text-lg" name="heart"></ion-icon> </button>
                                <a href="#">1,300</a>
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button" class="button__ico bg-slate-100 dark:bg-slate-700"> <ion-icon class="text-lg" name="chatbubble-ellipses"></ion-icon> </button>
                                <span>260</span>
                            </div>
                            <button type="button" class="button__ico ml-auto"> <ion-icon class="text-xl" name="share-outline"></ion-icon> </button>
                            <button type="button" class="button__ico"> <ion-icon class="text-xl" name="bookmark-outline"></ion-icon> </button>
                        </div>
                    </div>

                </div>

                <div class="p-5 h-full overflow-y-auto flex-1">

                    <!-- comment list -->
                    <div class="relative text-sm font-medium space-y-5"> 
                    <?php if (!empty($post['Comments'])): ?>
                        <?php foreach ($post['Comments'] as $comment): ?>
                        <div class="flex items-start gap-3 relative">
                            <img src="assets/images/avatars/avatar-2.jpg" alt="" class="w-6 h-6 mt-1 rounded-full">
                            <div class="flex-1">
                                <a href="#" class="text-black font-medium inline-block dark:text-white"> <?php echo htmlspecialchars($comment['Username']); ?> </a>
                                <p class="mt-0.5"><?php echo htmlspecialchars($comment['CommentContent']); ?> </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                                <?php else: ?>
                                    <p>No comments yet.</p>
                                <?php endif; ?>
                         
                    </div>

                </div>

                <form method="POST" action="comment.php">
                <input type="hidden" name="PostID" value="<?php echo htmlspecialchars($post['PostID']); ?>"> 
                <div class="bg-white p-3 text-sm font-medium flex items-center gap-2">
                       
                    <img src="assets/images/avatars/avatar-2.jpg" alt="" class="w-6 h-6 rounded-full">
                    
                    <div class="flex-1 relative overflow-hidden ">
                        <textarea name="CommentContent" placeholder="Add Comment...." rows="1" class="w-full resize-  px-4 py-2 focus:!border-transparent focus:!ring-transparent resize-y"></textarea>

                        <div class="flex items-center gap-2 absolute bottom-0.5 right-0 m-3">
                            <ion-icon class="text-xl flex text-blue-700" name="image"></ion-icon> 
                            <ion-icon class="text-xl flex text-yellow-500" name="happy"></ion-icon> 
                        </div>

                    </div>

                    <button type="submit" text-sm rounded-full py-1.5 px-4 font-semibold bg-secondery"> Replay</button>
              
                </div>
                </form>

            </div>
   
        </div>
        
    </div>
    <?php endforeach; ?>
        <?php else: ?>
            <p>No posts available.</p>
<?php endif; ?>


    <!-- ===========START POST============ -->
    <!-- create status -->
    <div class="hidden lg:p-20 uk- open" id="create-status" uk-modal="">
   
        <div class="uk-modal-dialog tt relative overflow-hidden mx-auto bg-white shadow-xl rounded-lg md:w-[520px] w-full dark:bg-dark2">

            <div class="text-center py-4 border-b mb-0 dark:border-slate-700">
                <h2 class="text-sm font-medium text-black"> Create Status </h2>

                <!-- close button -->
                <button type="button" class="button-icon absolute top-0 right-0 m-2.5 uk-modal-close">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
     
            </div>

            <!-- create post -->
            <form action="create-post.php" method="POST" enctype="multipart/form-data">   
            <div class="space-y-5 mt-3 p-2">
                <textarea class="w-full !text-black placeholder:!text-black !bg-white !border-transparent focus:!border-transparent focus:!ring-transparent !font-normal !text-xl   dark:!text-white dark:placeholder:!text-white dark:!bg-slate-800" name="post_content" id=""  rows="6" placeholder="What do you have in mind?"></textarea>
            </div>
              <!-- File upload (optional) -->
              <div class="space-y-5 mt-3 p-2">
              <input type="file" name="post_image" accept="image/*"class="flex items-center gap-1.5 bg-sky-50 text-sky-600 rounded-full py-1 px-2 border-2 border-sky-100 dark:bg-sky-950 dark:border-sky-900"><br>
              </div>

            <div class="flex items-center gap-2 text-sm py-2 px-4 font-medium flex-wrap">
                
                <button type="button" class="flex items-center gap-1.5 bg-sky-50 text-sky-600 rounded-full py-1 px-2 border-2 border-sky-100 dark:bg-sky-950 dark:border-sky-900">  
                    <ion-icon name="image" class="text-base"></ion-icon>  
                    Image 
                </button>
                <button type="button" class="flex items-center gap-1.5 bg-teal-50 text-teal-600 rounded-full py-1 px-2 border-2 border-teal-100 dark:bg-teal-950 dark:border-teal-900">  
                    <ion-icon name="videocam" class="text-base"></ion-icon>  
                    Video 
                </button>
                <button type="button" class="flex items-center gap-1.5 bg-orange-50 text-orange-600 rounded-full py-1 px-2 border-2 border-orange-100 dark:bg-yellow-950 dark:border-yellow-900">  
                    <ion-icon name="happy" class="text-base"></ion-icon>  
                    Feeling
                </button>
                <button type="button" class="flex items-center gap-1.5 bg-red-50 text-red-600 rounded-full py-1 px-2 border-2 border-rose-100 dark:bg-rose-950 dark:border-rose-900">  
                    <ion-icon name="location" class="text-base"></ion-icon>  
                    Check in 
                </button>
                <button type="button" class="grid place-items-center w-8 h-8 text-xl rounded-full bg-secondery">  
                    <ion-icon name="ellipsis-horizontal"></ion-icon>
                </button>
            </div>
 
            <div class="p-5 flex justify-between items-center">
                <div>
                    <button class="inline-flex items-center py-1 px-2.5 gap-1 font-medium text-sm rounded-full bg-slate-50 border-2 border-slate-100 group aria-expanded:bg-slate-100 aria-expanded: dark:text-white dark:bg-slate-700 dark:border-slate-600" type="button"> 
                        Everyone
                        <ion-icon name="chevron-down-outline" class="text-base duration-500 group-aria-expanded:rotate-180"></ion-icon> 
                    </button>

                    <div  class="p-2 bg-white rounded-lg shadow-lg text-black font-medium border border-slate-100 w-60 dark:bg-slate-700"
                          uk-drop="offset:10;pos: bottom-left; reveal-left;animate-out: true; animation: uk-animation-scale-up uk-transform-origin-bottom-left ; mode:click">
                               
                        
                            <label>
                                <input type="radio" name="radio-status" id="monthly1" class="peer appearance-none hidden" checked />
                                <div class=" relative flex items-center justify-between cursor-pointer rounded-md p-2 px-3 hover:bg-secondery peer-checked:[&_.active]:block dark:bg-dark3">
                                    <div class="text-sm">  Everyone </div> 
                                    <ion-icon name="checkmark-circle" class="hidden active absolute -translate-y-1/2 right-2 text-2xl text-blue-600 uk-animation-scale-up"></ion-icon>
                                </div>
                            </label>
                            <label>
                                <input type="radio" name="radio-status" id="monthly1" class="peer appearance-none hidden" />
                                <div class=" relative flex items-center justify-between cursor-pointer rounded-md p-2 px-3 hover:bg-secondery peer-checked:[&_.active]:block dark:bg-dark3">
                                    <div class="text-sm"> Friends </div> 
                                    <ion-icon name="checkmark-circle" class="hidden active absolute -translate-y-1/2 right-2 text-2xl text-blue-600 uk-animation-scale-up"></ion-icon>
                                </div>
                            </label>
                            <label>
                                <input type="radio" name="radio-status" id="monthly" class="peer appearance-none hidden" />
                                <div class=" relative flex items-center justify-between cursor-pointer rounded-md p-2 px-3 hover:bg-secondery peer-checked:[&_.active]:block dark:bg-dark3">
                                    <div class="text-sm"> Only me </div> 
                                    <ion-icon name="checkmark-circle" class="hidden active absolute -translate-y-1/2 right-2 text-2xl text-blue-600 uk-animation-scale-up"></ion-icon>
                                </div>
                            </label>
                       

                    </div>
                </div>
                <div class="flex items-center gap-2"> 
                    <button type="submit" name="submit_post" class="button bg-blue-500 text-white py-2 px-12 text-[14px]"> Create</button>
                </div>
                </form>
            </div>
        
        </div>

    </div>


<!-- ===========END POST============ -->


    <!-- create story -->
    <div class="hidden lg:p-20" id="create-story" uk-modal="">
   
        <div class="uk-modal-dialog tt relative overflow-hidden mx-auto bg-white p-7 shadow-xl rounded-lg md:w-[520px] w-full dark:bg-dark2">

            <div class="text-center py-3 border-b -m-7 mb-0 dark:border-slate-700">
                <h2 class="text-sm font-medium"> Create Status </h2>

                <!-- close button -->
                <button type="button" class="button__ico absolute top-0 right-0 m-2.5 uk-modal-close">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
     
            </div>
                    
            <div class="space-y-5 mt-7">

                <div> 
                    <label for="" class="text-base">What do you have in mind? </label>
                    <input type="text"  class="w-full mt-3">
                </div>

                <div>  
                    <div class="w-full h-72 relative border1 rounded-lg overflow-hidden bg-[url('../images/ad_pattern.png')] bg-repeat">
                    
                        <label for="createStatusUrl" class="flex flex-col justify-center items-center absolute -translate-x-1/2 left-1/2 bottom-0 z-10 w-full pb-6 pt-10 cursor-pointer bg-gradient-to-t from-gray-700/60">
                            <input id="createStatusUrl" type="file" class="hidden" />
                            <ion-icon name="image" class="text-3xl text-teal-600"></ion-icon>
                            <span class="text-white mt-2">Browse to Upload image </span>
                        </label>

                        <img id="createStatusImage" src="#" alt="Uploaded Image" accept="image/png, image/jpeg" style="display:none;" class="w-full h-full absolute object-cover">
                    </div>

                </div>
                
                <div class="flex justify-between items-center">
                    <div class="flex items-start gap-2">
                        <ion-icon name="time-outline" class="text-3xl text-sky-600  rounded-full bg-blue-50 dark:bg-transparent"></ion-icon>
                        <p class="text-sm text-gray-500 font-medium"> Your Status will be available <br> for <span class="text-gray-800"> 24 Hours</span> </p>
                    </div>

                    <button type="button" class="button bg-blue-500 text-white px-8"> Create</button>
                </div>
            </div>
        
        </div>

    </div>

    <!-- Javascript  -->
     <!-- script for like functionality -->
    <script>
        function likePost(postID) {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "like.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onload = function () {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        const likeCount = document.getElementById(`like-count-${postID}`);
                        likeCount.textContent = response.totalLikes;
                    } else {
                        alert(response.message);
                    }
                }
            };
            xhr.send("postID=" + postID);
        }
    </script>
    <script src="assets/js/uikit.min.js"></script>
    <script src="assets/js/simplebar.js"></script>
    <script src="assets/js/script.js"></script>
 
    <!-- Ion icon -->
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
</body>
</html>