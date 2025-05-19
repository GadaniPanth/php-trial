<?php
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json");

    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "api 1";

    $conn = new mysqli($host, $username, $password, $database);
    if ($conn->connect_error) {
        die(json_encode(["status" => "0", "message" => "DB connection failed."]));
    }


    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $imgName = 'Default.jpg';
        $query = '';
        $params = [];
        $paramTypes = '';

        if (isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmtSelect = $conn->prepare("SELECT id, name, email, image FROM users WHERE id = ?");
            $stmtSelect->bind_param("i", $id);
            $stmtSelect->execute();
            $result = $stmtSelect->get_result();
            $user = $result->fetch_assoc();
            $stmtSelect->close();

            if (!$user) {
                echo json_encode(["status" => "0", "message" => "User not found."]);
                exit;
            }

            $name = !empty($name) ? $name : $user['name'];
            $email = !empty($email) ? $email : $user['email'];
            $imgName = $user['image'];
            $query = "UPDATE users SET name = ?, email = ?, image = ? WHERE id = ?";
            $paramTypes = "sssi";
            $params = [$name, $email, $imgName, $id];
        } else {
            if (empty($name) || empty($email)) {
                echo json_encode(["status" => "0", "message" => "Name and Email are required!"]);
                exit;
            }

            $query = "INSERT INTO users (name, email, image) VALUES (?, ?, ?)";
            $paramTypes = "sss";
            $params = [$name, $email, $imgName];
        }

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imgName = basename($_FILES['image']['name']);
            $tmpPath = $_FILES['image']['tmp_name'];
            $uploadDir = "uploads/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filePath = $uploadDir . $imgName;
            if (!move_uploaded_file($tmpPath, $filePath)) {
                echo json_encode(["status" => "0", "message" => "Failed to upload image."]);
                exit;
            }
            $params[2] = $imgName;
        }

        try {
            $stmt = $conn->prepare($query);
            if ($stmt === false) {
                throw new Exception($conn->error);
            }
            $stmt->bind_param($paramTypes, ...$params);
            $stmt->execute();

            $message = isset($id) ? "User updated." : "User added.";
            echo json_encode(["status" => "1", "message" => $message]);
            $stmt->close();
        } catch (mysqli_sql_exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                echo json_encode(["status" => "0", "message" => "Email already exists."]);
            } else {
                echo json_encode(["status" => "0", "message" => "Database error: " . $e->getMessage()]);
            }
        }
    }
    
    
    else if ($_SERVER["REQUEST_METHOD"] === "GET") {
        if(isset($_GET['id'])){
            $id = intval($_GET['id']);
            $stmt = $conn->prepare("SELECT id, name, email, image FROM users where id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $user['image_url'] = "http://localhost/Panth/Api1/uploads/" . $user['image'];
                echo json_encode(["status" => "1", "user" => $user]);
            } else {
                $msg = "User not found with id " . $id;
                echo json_encode(["status" => "0", "message" => $msg]);
            }
            $stmt->close();
        }else {
            $sql = "SELECT id, name, email, image FROM users";
            $result = $conn->query($sql);

            if ($result && $result->num_rows > 0) {
                $users = [];
                while ($row = $result->fetch_assoc()) {
                    $row['image_url'] = "http://localhost/Panth/Api1/uploads/" . $row['image'];
                    $users[] = $row;
                }
                echo json_encode(["status" => "1", "users" => $users]);
            } else {
                echo json_encode(["status" => "0", "message" => "No users found."]);
            }
        }
    } 
    
    
    else if($_SERVER["REQUEST_METHOD"] === "DELETE") {
        if(isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt-> execute();

            if($stmt->affected_rows > 0){
                $msg = "User Deleted of id " . $id;
                echo json_encode(["status" => "1", "message" => $msg]);
            }else {
                $msg= "User not Found with id " . $id;
                echo json_encode(["status" => "0", "message"=> $msg ]);
            }
        } else {
            echo json_encode(["status" => "0", "message"=> "Id Required!"]);
        }
    }
    else {
        echo json_encode(["status"=> "0", "message" => "Valid request methods are: Get, Post, Delete.(Update is valid with ID in post)"]);
    }
    $conn->close();
?>
