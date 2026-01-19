<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MockUsersSeeder extends Seeder
{
    // ==================== NAMES DATABASE ====================

    private $maleNames = [
        'western' => [
            'James', 'John', 'Michael', 'David', 'Chris', 'Daniel', 'Matthew', 'Andrew', 'Ryan', 'Brandon',
            'Tyler', 'Kevin', 'Brian', 'Eric', 'Steven', 'Jason', 'Justin', 'Aaron', 'Adam', 'Nathan',
            'William', 'Richard', 'Joseph', 'Thomas', 'Charles', 'Mark', 'Donald', 'George', 'Paul', 'Robert',
            'Anthony', 'Joshua', 'Kenneth', 'Edward', 'Ronald', 'Timothy', 'Stephen', 'Larry', 'Frank', 'Scott',
            'Jack', 'Oliver', 'Harry', 'Leo', 'Oscar', 'Archie', 'Henry', 'Charlie', 'Arthur', 'Noah',
            'Liam', 'Ethan', 'Mason', 'Lucas', 'Logan', 'Alexander', 'Sebastian', 'Benjamin', 'Elijah', 'Aiden',
            'Connor', 'Dylan', 'Luke', 'Jake', 'Max', 'Sam', 'Ben', 'Tom', 'Joe', 'Nick',
            'Patrick', 'Sean', 'Colin', 'Declan', 'Finn', 'Luca', 'Felix', 'Hugo', 'Theo', 'Miles'
        ],
        'asian' => [
            // Vietnamese
            'Minh', 'Tuan', 'Duc', 'Long', 'Hung', 'Hieu', 'Quan', 'Khoa', 'Dat', 'Phuc',
            'Thanh', 'Huy', 'Khanh', 'Nam', 'Tien', 'Cuong', 'Dung', 'Bao', 'Vinh', 'Tai',
            'Truong', 'Quang', 'Son', 'Hoang', 'Viet', 'Hai', 'Trung', 'An', 'Binh', 'Phat',
            // Chinese
            'Wei', 'Ming', 'Tao', 'Jun', 'Hao', 'Chen', 'Yang', 'Feng', 'Lei', 'Bo',
            'Jie', 'Xin', 'Qiang', 'Chao', 'Peng', 'Gang', 'Dong', 'Hai', 'Jian', 'Yong',
            // Japanese
            'Kenji', 'Yuki', 'Takeshi', 'Ryu', 'Haruto', 'Sota', 'Yuto', 'Ren', 'Kaito', 'Asahi',
            'Hiroto', 'Minato', 'Yamato', 'Sora', 'Hayato', 'Kento', 'Shota', 'Ryota', 'Daiki', 'Yuma',
            // Korean
            'Jin', 'Seojun', 'Minho', 'Jihoon', 'Dohyun', 'Jungwoo', 'Hyunwoo', 'Taehyung', 'Jimin', 'Seungmin',
            'Woojin', 'Minsu', 'Jaehyun', 'Siwoo', 'Hajun', 'Eunwoo', 'Jiho', 'Yunho', 'Taemin', 'Changmin',
            // Thai
            'Somchai', 'Tanawat', 'Pichit', 'Nattapong', 'Wichai', 'Surasak', 'Kritsada', 'Thanakorn', 'Pattanapong', 'Sirichai'
        ],
        'latin' => [
            'Carlos', 'Diego', 'Miguel', 'Jose', 'Luis', 'Antonio', 'Pedro', 'Rafael', 'Fernando', 'Alejandro',
            'Ricardo', 'Marco', 'Pablo', 'Sergio', 'Javier', 'Roberto', 'Manuel', 'Francisco', 'Eduardo', 'Andres',
            'Gabriel', 'Santiago', 'Mateo', 'Sebastian', 'Nicolas', 'Samuel', 'Emiliano', 'Leonardo', 'Daniel', 'Adrian',
            'Juan', 'Jorge', 'Raul', 'Oscar', 'Hector', 'Victor', 'Cesar', 'Rodrigo', 'Arturo', 'Enrique',
            'Bruno', 'Thiago', 'Enzo', 'Lucas', 'Gustavo', 'Felipe', 'Marcelo', 'Joao', 'Bernardo', 'Lorenzo'
        ],
        'arabic' => [
            'Ahmed', 'Mohammed', 'Omar', 'Ali', 'Hassan', 'Khalid', 'Youssef', 'Ibrahim', 'Karim', 'Tariq',
            'Samir', 'Fadi', 'Nabil', 'Walid', 'Rami', 'Sami', 'Bassem', 'Amr', 'Hossam', 'Ziad',
            'Mustafa', 'Adel', 'Mahmoud', 'Ayman', 'Hamza', 'Bilal', 'Rashid', 'Faisal', 'Salem', 'Nasser',
            'Younis', 'Marwan', 'Tarek', 'Kareem', 'Jawad', 'Imad', 'Fouad', 'Majid', 'Jamal', 'Fahad'
        ],
        'indian' => [
            'Arjun', 'Rahul', 'Vikram', 'Raj', 'Amit', 'Rohan', 'Aditya', 'Sanjay', 'Pradeep', 'Suresh',
            'Rajesh', 'Vijay', 'Anand', 'Krishna', 'Ravi', 'Deepak', 'Manoj', 'Nikhil', 'Varun', 'Karan',
            'Akash', 'Harsh', 'Yash', 'Shiva', 'Aarav', 'Vivaan', 'Reyansh', 'Ayaan', 'Ishaan', 'Vihaan'
        ],
        'russian' => [
            'Alexei', 'Dmitri', 'Ivan', 'Mikhail', 'Nikolai', 'Pavel', 'Sergei', 'Vladimir', 'Andrei', 'Boris',
            'Viktor', 'Yuri', 'Oleg', 'Igor', 'Maxim', 'Anton', 'Denis', 'Roman', 'Artem', 'Kirill'
        ],
        'african' => [
            'Kwame', 'Kofi', 'Ade', 'Chidi', 'Emeka', 'Olumide', 'Tunde', 'Yemi', 'Ayo', 'Femi',
            'Jamal', 'Malik', 'Tariq', 'Darius', 'Marcus', 'Xavier', 'Terrell', 'DeShawn', 'Jalen', 'Tyrone'
        ]
    ];

    private $femaleNames = [
        'western' => [
            'Emma', 'Olivia', 'Sophia', 'Isabella', 'Mia', 'Charlotte', 'Amelia', 'Harper', 'Evelyn', 'Abigail',
            'Emily', 'Elizabeth', 'Sofia', 'Avery', 'Ella', 'Scarlett', 'Grace', 'Victoria', 'Riley', 'Aria',
            'Lily', 'Chloe', 'Zoey', 'Hannah', 'Natalie', 'Leah', 'Savannah', 'Audrey', 'Brooklyn', 'Claire',
            'Lucy', 'Anna', 'Samantha', 'Caroline', 'Genesis', 'Aaliyah', 'Kennedy', 'Stella', 'Maya', 'Naomi',
            'Sarah', 'Allison', 'Gabriella', 'Madelyn', 'Hailey', 'Katherine', 'Aurora', 'Bella', 'Alice', 'Violet',
            'Jessica', 'Rachel', 'Ashley', 'Jennifer', 'Nicole', 'Stephanie', 'Amanda', 'Lauren', 'Megan', 'Brittany',
            'Rose', 'Ruby', 'Ivy', 'Willow', 'Luna', 'Penelope', 'Eleanor', 'Hazel', 'Nora', 'Ellie'
        ],
        'asian' => [
            // Vietnamese
            'Linh', 'Trang', 'Hoa', 'Mai', 'Lan', 'Ngoc', 'Thao', 'Huong', 'Thu', 'Phuong',
            'Anh', 'Hang', 'Hien', 'Yen', 'Dung', 'Chi', 'Trinh', 'Van', 'Nhu', 'Thi',
            'Quynh', 'Ha', 'My', 'Uyen', 'Khanh', 'Ngan', 'Duyen', 'Tram', 'Vy', 'Bich',
            // Chinese
            'Xiaoli', 'Mei', 'Ling', 'Yan', 'Fang', 'Jing', 'Hui', 'Xia', 'Yue', 'Qian',
            'Na', 'Dan', 'Juan', 'Wen', 'Ping', 'Hong', 'Zhen', 'Yi', 'Rong', 'Xue',
            // Japanese
            'Yuki', 'Sakura', 'Hana', 'Aoi', 'Yui', 'Rin', 'Mio', 'Saki', 'Hinata', 'Akari',
            'Mei', 'Nana', 'Yuna', 'Ayaka', 'Haruka', 'Kaori', 'Misaki', 'Emi', 'Riko', 'Asuka',
            // Korean
            'Yuna', 'Mina', 'Sora', 'Jieun', 'Minji', 'Yerin', 'Suzy', 'Jisoo', 'Seulgi', 'Irene',
            'Yeji', 'Nayeon', 'Dahyun', 'Chaeyoung', 'Jennie', 'Rose', 'Lisa', 'Sana', 'Momo', 'Tzuyu',
            // Thai
            'Ploy', 'Fah', 'Noon', 'Aom', 'Punch', 'Bella', 'Mint', 'Pim', 'Ice', 'Milk'
        ],
        'latin' => [
            'Maria', 'Sofia', 'Valentina', 'Camila', 'Isabella', 'Lucia', 'Elena', 'Ana', 'Paula', 'Daniela',
            'Mariana', 'Gabriela', 'Carolina', 'Andrea', 'Nicole', 'Fernanda', 'Alejandra', 'Victoria', 'Natalia', 'Adriana',
            'Valeria', 'Catalina', 'Jimena', 'Martina', 'Renata', 'Sara', 'Emilia', 'Antonella', 'Florencia', 'Julieta',
            'Carmen', 'Laura', 'Patricia', 'Rosa', 'Alicia', 'Veronica', 'Claudia', 'Monica', 'Diana', 'Silvia',
            'Julia', 'Beatriz', 'Leticia', 'Bianca', 'Larissa', 'Bruna', 'Amanda', 'Raquel', 'Isadora', 'Helena'
        ],
        'arabic' => [
            'Fatima', 'Aisha', 'Maryam', 'Layla', 'Sara', 'Noor', 'Hana', 'Yasmin', 'Leila', 'Amira',
            'Dalia', 'Rania', 'Salma', 'Dana', 'Lina', 'Maya', 'Nadia', 'Rana', 'Zeina', 'Farah',
            'Huda', 'Reem', 'Noura', 'Mariam', 'Rasha', 'Samira', 'Dina', 'Aya', 'Hiba', 'Malak',
            'Zara', 'Lara', 'Sama', 'Jana', 'Tala', 'Shahd', 'Arwa', 'Razan', 'Lamis', 'Yara'
        ],
        'indian' => [
            'Priya', 'Neha', 'Anjali', 'Pooja', 'Shreya', 'Divya', 'Kavya', 'Ananya', 'Aishwarya', 'Isha',
            'Riya', 'Simran', 'Kritika', 'Sakshi', 'Tanvi', 'Meera', 'Nisha', 'Nikita', 'Aditi', 'Deepika',
            'Sneha', 'Kiara', 'Diya', 'Aaradhya', 'Myra', 'Anika', 'Saanvi', 'Pari', 'Avni', 'Aadhya'
        ],
        'russian' => [
            'Anastasia', 'Natasha', 'Svetlana', 'Olga', 'Tatiana', 'Elena', 'Irina', 'Maria', 'Anna', 'Ekaterina',
            'Yulia', 'Ksenia', 'Daria', 'Polina', 'Alina', 'Victoria', 'Kristina', 'Veronika', 'Sofia', 'Alexandra'
        ],
        'african' => [
            'Amara', 'Nia', 'Zuri', 'Imani', 'Aaliyah', 'Jasmine', 'Destiny', 'Diamond', 'Ebony', 'Jade',
            'Naomi', 'Keisha', 'Tamika', 'Latoya', 'Shaniqua', 'Monique', 'Tiffany', 'Crystal', 'Shanice', 'Briana'
        ]
    ];

    private $lastNames = [
        'western' => [
            'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
            'Wilson', 'Anderson', 'Taylor', 'Thomas', 'Moore', 'Jackson', 'Martin', 'Lee', 'Thompson', 'White',
            'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker', 'Young', 'Allen', 'King',
            'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores', 'Green', 'Adams', 'Nelson', 'Baker',
            'Murphy', 'Kelly', 'Sullivan', 'Walsh', 'O\'Brien', 'Ryan', 'Connor', 'Kennedy', 'Riley', 'Burns'
        ],
        'asian' => [
            // Vietnamese
            'Nguyen', 'Tran', 'Le', 'Pham', 'Hoang', 'Vu', 'Vo', 'Dang', 'Bui', 'Do',
            'Ho', 'Ngo', 'Duong', 'Ly', 'Dinh', 'Truong', 'Huynh', 'Cao', 'Lam', 'Trinh',
            // Chinese
            'Wang', 'Zhang', 'Liu', 'Chen', 'Yang', 'Huang', 'Zhao', 'Wu', 'Zhou', 'Xu',
            'Sun', 'Ma', 'Zhu', 'Hu', 'Guo', 'Lin', 'He', 'Gao', 'Luo', 'Zheng',
            // Japanese
            'Tanaka', 'Yamamoto', 'Sato', 'Suzuki', 'Watanabe', 'Ito', 'Nakamura', 'Kobayashi', 'Kato', 'Yoshida',
            'Yamada', 'Sasaki', 'Yamaguchi', 'Matsumoto', 'Inoue', 'Kimura', 'Hayashi', 'Shimizu', 'Yamazaki', 'Mori',
            // Korean
            'Kim', 'Park', 'Lee', 'Choi', 'Jung', 'Kang', 'Cho', 'Yoon', 'Jang', 'Lim',
            'Han', 'Oh', 'Seo', 'Shin', 'Kwon', 'Hwang', 'Ahn', 'Song', 'Yoo', 'Hong'
        ],
        'latin' => [
            'Garcia', 'Rodriguez', 'Martinez', 'Lopez', 'Gonzalez', 'Hernandez', 'Perez', 'Sanchez', 'Ramirez', 'Torres',
            'Flores', 'Rivera', 'Gomez', 'Diaz', 'Reyes', 'Morales', 'Cruz', 'Ortiz', 'Gutierrez', 'Chavez',
            'Ramos', 'Romero', 'Ruiz', 'Alvarez', 'Mendoza', 'Vasquez', 'Castillo', 'Fernandez', 'Moreno', 'Jimenez',
            'Silva', 'Santos', 'Oliveira', 'Souza', 'Costa', 'Ferreira', 'Almeida', 'Carvalho', 'Ribeiro', 'Martins'
        ],
        'arabic' => [
            'Al-Ahmad', 'Al-Hassan', 'Al-Rashid', 'El-Sayed', 'Mansour', 'Nasser', 'Saleh', 'Haddad', 'Khoury', 'Abboud',
            'Amin', 'Farouk', 'Hamdan', 'Jaber', 'Khalil', 'Masri', 'Nasr', 'Qasim', 'Rashid', 'Sharif',
            'Abdallah', 'Bakri', 'Darwish', 'Fadel', 'Ghazal', 'Halabi', 'Issa', 'Jamil', 'Kassab', 'Lahoud'
        ],
        'indian' => [
            'Sharma', 'Patel', 'Singh', 'Kumar', 'Gupta', 'Reddy', 'Rao', 'Iyer', 'Shah', 'Nair',
            'Joshi', 'Verma', 'Mehta', 'Malhotra', 'Kapoor', 'Chopra', 'Bhatia', 'Saxena', 'Aggarwal', 'Banerjee',
            'Mukherjee', 'Chatterjee', 'Das', 'Ghosh', 'Sen', 'Roy', 'Dutta', 'Pillai', 'Menon', 'Krishnan'
        ],
        'russian' => [
            'Ivanov', 'Smirnov', 'Kuznetsov', 'Popov', 'Vasiliev', 'Petrov', 'Sokolov', 'Mikhailov', 'Novikov', 'Fedorov',
            'Morozov', 'Volkov', 'Alekseev', 'Lebedev', 'Semenov', 'Egorov', 'Pavlov', 'Kozlov', 'Stepanov', 'Nikolaev'
        ],
        'african' => [
            'Okafor', 'Adeyemi', 'Mensah', 'Owusu', 'Diallo', 'Traore', 'Kamara', 'Mbeki', 'Zulu', 'Ndlovu',
            'Nkosi', 'Dlamini', 'Moyo', 'Banda', 'Phiri', 'Mwangi', 'Ochieng', 'Kimani', 'Okonkwo', 'Abubakar'
        ]
    ];

    // ==================== LOCATIONS DATABASE ====================

    private $locations = [
        'VN' => [
            ['city' => 'Ho Chi Minh City', 'lat' => 10.8231, 'lng' => 106.6297],
            ['city' => 'Hanoi', 'lat' => 21.0285, 'lng' => 105.8542],
            ['city' => 'Da Nang', 'lat' => 16.0544, 'lng' => 108.2022],
            ['city' => 'Can Tho', 'lat' => 10.0452, 'lng' => 105.7469],
            ['city' => 'Nha Trang', 'lat' => 12.2388, 'lng' => 109.1967],
            ['city' => 'Hue', 'lat' => 16.4637, 'lng' => 107.5909],
            ['city' => 'Vung Tau', 'lat' => 10.4114, 'lng' => 107.1362],
            ['city' => 'Hai Phong', 'lat' => 20.8449, 'lng' => 106.6881],
            ['city' => 'Bien Hoa', 'lat' => 10.9574, 'lng' => 106.8426],
            ['city' => 'Binh Duong', 'lat' => 10.9804, 'lng' => 106.6519],
            ['city' => 'Da Lat', 'lat' => 11.9404, 'lng' => 108.4583],
            ['city' => 'Phu Quoc', 'lat' => 10.2899, 'lng' => 103.9840],
        ],
        'US' => [
            ['city' => 'New York', 'lat' => 40.7128, 'lng' => -74.0060],
            ['city' => 'Los Angeles', 'lat' => 34.0522, 'lng' => -118.2437],
            ['city' => 'Chicago', 'lat' => 41.8781, 'lng' => -87.6298],
            ['city' => 'Houston', 'lat' => 29.7604, 'lng' => -95.3698],
            ['city' => 'Miami', 'lat' => 25.7617, 'lng' => -80.1918],
            ['city' => 'San Francisco', 'lat' => 37.7749, 'lng' => -122.4194],
            ['city' => 'Seattle', 'lat' => 47.6062, 'lng' => -122.3321],
            ['city' => 'Las Vegas', 'lat' => 36.1699, 'lng' => -115.1398],
            ['city' => 'Boston', 'lat' => 42.3601, 'lng' => -71.0589],
            ['city' => 'Atlanta', 'lat' => 33.7490, 'lng' => -84.3880],
            ['city' => 'San Diego', 'lat' => 32.7157, 'lng' => -117.1611],
            ['city' => 'Denver', 'lat' => 39.7392, 'lng' => -104.9903],
            ['city' => 'Austin', 'lat' => 30.2672, 'lng' => -97.7431],
            ['city' => 'Phoenix', 'lat' => 33.4484, 'lng' => -112.0740],
        ],
        'JP' => [
            ['city' => 'Tokyo', 'lat' => 35.6762, 'lng' => 139.6503],
            ['city' => 'Osaka', 'lat' => 34.6937, 'lng' => 135.5023],
            ['city' => 'Kyoto', 'lat' => 35.0116, 'lng' => 135.7681],
            ['city' => 'Yokohama', 'lat' => 35.4437, 'lng' => 139.6380],
            ['city' => 'Fukuoka', 'lat' => 33.5904, 'lng' => 130.4017],
            ['city' => 'Sapporo', 'lat' => 43.0618, 'lng' => 141.3545],
            ['city' => 'Kobe', 'lat' => 34.6901, 'lng' => 135.1956],
            ['city' => 'Nagoya', 'lat' => 35.1815, 'lng' => 136.9066],
            ['city' => 'Okinawa', 'lat' => 26.2124, 'lng' => 127.6809],
        ],
        'KR' => [
            ['city' => 'Seoul', 'lat' => 37.5665, 'lng' => 126.9780],
            ['city' => 'Busan', 'lat' => 35.1796, 'lng' => 129.0756],
            ['city' => 'Incheon', 'lat' => 37.4563, 'lng' => 126.7052],
            ['city' => 'Daegu', 'lat' => 35.8714, 'lng' => 128.6014],
            ['city' => 'Daejeon', 'lat' => 36.3504, 'lng' => 127.3845],
            ['city' => 'Gwangju', 'lat' => 35.1595, 'lng' => 126.8526],
            ['city' => 'Jeju', 'lat' => 33.4996, 'lng' => 126.5312],
        ],
        'TH' => [
            ['city' => 'Bangkok', 'lat' => 13.7563, 'lng' => 100.5018],
            ['city' => 'Chiang Mai', 'lat' => 18.7883, 'lng' => 98.9853],
            ['city' => 'Phuket', 'lat' => 7.8804, 'lng' => 98.3923],
            ['city' => 'Pattaya', 'lat' => 12.9236, 'lng' => 100.8825],
            ['city' => 'Krabi', 'lat' => 8.0863, 'lng' => 98.9063],
            ['city' => 'Hua Hin', 'lat' => 12.5684, 'lng' => 99.9577],
            ['city' => 'Koh Samui', 'lat' => 9.5120, 'lng' => 100.0136],
        ],
        'PH' => [
            ['city' => 'Manila', 'lat' => 14.5995, 'lng' => 120.9842],
            ['city' => 'Cebu', 'lat' => 10.3157, 'lng' => 123.8854],
            ['city' => 'Davao', 'lat' => 7.1907, 'lng' => 125.4553],
            ['city' => 'Makati', 'lat' => 14.5547, 'lng' => 121.0244],
            ['city' => 'Quezon City', 'lat' => 14.6760, 'lng' => 121.0437],
            ['city' => 'Boracay', 'lat' => 11.9674, 'lng' => 121.9248],
        ],
        'SG' => [
            ['city' => 'Singapore Central', 'lat' => 1.3521, 'lng' => 103.8198],
            ['city' => 'Orchard', 'lat' => 1.3048, 'lng' => 103.8318],
            ['city' => 'Marina Bay', 'lat' => 1.2834, 'lng' => 103.8607],
            ['city' => 'Sentosa', 'lat' => 1.2494, 'lng' => 103.8303],
        ],
        'MY' => [
            ['city' => 'Kuala Lumpur', 'lat' => 3.1390, 'lng' => 101.6869],
            ['city' => 'Penang', 'lat' => 5.4164, 'lng' => 100.3327],
            ['city' => 'Johor Bahru', 'lat' => 1.4927, 'lng' => 103.7414],
            ['city' => 'Langkawi', 'lat' => 6.3500, 'lng' => 99.8000],
            ['city' => 'Kota Kinabalu', 'lat' => 5.9804, 'lng' => 116.0735],
        ],
        'ID' => [
            ['city' => 'Jakarta', 'lat' => -6.2088, 'lng' => 106.8456],
            ['city' => 'Bali', 'lat' => -8.3405, 'lng' => 115.0920],
            ['city' => 'Surabaya', 'lat' => -7.2575, 'lng' => 112.7521],
            ['city' => 'Bandung', 'lat' => -6.9175, 'lng' => 107.6191],
            ['city' => 'Yogyakarta', 'lat' => -7.7956, 'lng' => 110.3695],
        ],
        'TW' => [
            ['city' => 'Taipei', 'lat' => 25.0330, 'lng' => 121.5654],
            ['city' => 'Kaohsiung', 'lat' => 22.6273, 'lng' => 120.3014],
            ['city' => 'Taichung', 'lat' => 24.1477, 'lng' => 120.6736],
            ['city' => 'Tainan', 'lat' => 22.9999, 'lng' => 120.2269],
        ],
        'HK' => [
            ['city' => 'Hong Kong Central', 'lat' => 22.2800, 'lng' => 114.1588],
            ['city' => 'Kowloon', 'lat' => 22.3193, 'lng' => 114.1694],
            ['city' => 'Tsim Sha Tsui', 'lat' => 22.2988, 'lng' => 114.1722],
        ],
        'GB' => [
            ['city' => 'London', 'lat' => 51.5074, 'lng' => -0.1278],
            ['city' => 'Manchester', 'lat' => 53.4808, 'lng' => -2.2426],
            ['city' => 'Birmingham', 'lat' => 52.4862, 'lng' => -1.8904],
            ['city' => 'Liverpool', 'lat' => 53.4084, 'lng' => -2.9916],
            ['city' => 'Edinburgh', 'lat' => 55.9533, 'lng' => -3.1883],
            ['city' => 'Glasgow', 'lat' => 55.8642, 'lng' => -4.2518],
        ],
        'FR' => [
            ['city' => 'Paris', 'lat' => 48.8566, 'lng' => 2.3522],
            ['city' => 'Lyon', 'lat' => 45.7640, 'lng' => 4.8357],
            ['city' => 'Marseille', 'lat' => 43.2965, 'lng' => 5.3698],
            ['city' => 'Nice', 'lat' => 43.7102, 'lng' => 7.2620],
            ['city' => 'Bordeaux', 'lat' => 44.8378, 'lng' => -0.5792],
        ],
        'DE' => [
            ['city' => 'Berlin', 'lat' => 52.5200, 'lng' => 13.4050],
            ['city' => 'Munich', 'lat' => 48.1351, 'lng' => 11.5820],
            ['city' => 'Hamburg', 'lat' => 53.5511, 'lng' => 9.9937],
            ['city' => 'Frankfurt', 'lat' => 50.1109, 'lng' => 8.6821],
            ['city' => 'Cologne', 'lat' => 50.9375, 'lng' => 6.9603],
        ],
        'AU' => [
            ['city' => 'Sydney', 'lat' => -33.8688, 'lng' => 151.2093],
            ['city' => 'Melbourne', 'lat' => -37.8136, 'lng' => 144.9631],
            ['city' => 'Brisbane', 'lat' => -27.4698, 'lng' => 153.0251],
            ['city' => 'Perth', 'lat' => -31.9505, 'lng' => 115.8605],
            ['city' => 'Gold Coast', 'lat' => -28.0167, 'lng' => 153.4000],
        ],
        'CA' => [
            ['city' => 'Toronto', 'lat' => 43.6532, 'lng' => -79.3832],
            ['city' => 'Vancouver', 'lat' => 49.2827, 'lng' => -123.1207],
            ['city' => 'Montreal', 'lat' => 45.5017, 'lng' => -73.5673],
            ['city' => 'Calgary', 'lat' => 51.0447, 'lng' => -114.0719],
        ],
        'BR' => [
            ['city' => 'Sao Paulo', 'lat' => -23.5505, 'lng' => -46.6333],
            ['city' => 'Rio de Janeiro', 'lat' => -22.9068, 'lng' => -43.1729],
            ['city' => 'Brasilia', 'lat' => -15.7975, 'lng' => -47.8919],
            ['city' => 'Salvador', 'lat' => -12.9714, 'lng' => -38.5014],
            ['city' => 'Fortaleza', 'lat' => -3.7319, 'lng' => -38.5267],
        ],
        'MX' => [
            ['city' => 'Mexico City', 'lat' => 19.4326, 'lng' => -99.1332],
            ['city' => 'Guadalajara', 'lat' => 20.6597, 'lng' => -103.3496],
            ['city' => 'Cancun', 'lat' => 21.1619, 'lng' => -86.8515],
            ['city' => 'Monterrey', 'lat' => 25.6866, 'lng' => -100.3161],
            ['city' => 'Playa del Carmen', 'lat' => 20.6296, 'lng' => -87.0739],
        ],
        'AE' => [
            ['city' => 'Dubai', 'lat' => 25.2048, 'lng' => 55.2708],
            ['city' => 'Abu Dhabi', 'lat' => 24.4539, 'lng' => 54.3773],
            ['city' => 'Sharjah', 'lat' => 25.3463, 'lng' => 55.4209],
        ],
        'IN' => [
            ['city' => 'Mumbai', 'lat' => 19.0760, 'lng' => 72.8777],
            ['city' => 'Delhi', 'lat' => 28.7041, 'lng' => 77.1025],
            ['city' => 'Bangalore', 'lat' => 12.9716, 'lng' => 77.5946],
            ['city' => 'Chennai', 'lat' => 13.0827, 'lng' => 80.2707],
            ['city' => 'Hyderabad', 'lat' => 17.3850, 'lng' => 78.4867],
            ['city' => 'Kolkata', 'lat' => 22.5726, 'lng' => 88.3639],
            ['city' => 'Goa', 'lat' => 15.2993, 'lng' => 74.1240],
        ],
        'RU' => [
            ['city' => 'Moscow', 'lat' => 55.7558, 'lng' => 37.6173],
            ['city' => 'Saint Petersburg', 'lat' => 59.9311, 'lng' => 30.3609],
            ['city' => 'Sochi', 'lat' => 43.6028, 'lng' => 39.7342],
        ],
        'ES' => [
            ['city' => 'Madrid', 'lat' => 40.4168, 'lng' => -3.7038],
            ['city' => 'Barcelona', 'lat' => 41.3851, 'lng' => 2.1734],
            ['city' => 'Valencia', 'lat' => 39.4699, 'lng' => -0.3763],
            ['city' => 'Seville', 'lat' => 37.3891, 'lng' => -5.9845],
            ['city' => 'Ibiza', 'lat' => 38.9067, 'lng' => 1.4206],
        ],
        'IT' => [
            ['city' => 'Rome', 'lat' => 41.9028, 'lng' => 12.4964],
            ['city' => 'Milan', 'lat' => 45.4642, 'lng' => 9.1900],
            ['city' => 'Venice', 'lat' => 45.4408, 'lng' => 12.3155],
            ['city' => 'Florence', 'lat' => 43.7696, 'lng' => 11.2558],
            ['city' => 'Naples', 'lat' => 40.8518, 'lng' => 14.2681],
        ],
        'NL' => [
            ['city' => 'Amsterdam', 'lat' => 52.3676, 'lng' => 4.9041],
            ['city' => 'Rotterdam', 'lat' => 51.9244, 'lng' => 4.4777],
            ['city' => 'The Hague', 'lat' => 52.0705, 'lng' => 4.3007],
        ],
        'ZA' => [
            ['city' => 'Cape Town', 'lat' => -33.9249, 'lng' => 18.4241],
            ['city' => 'Johannesburg', 'lat' => -26.2041, 'lng' => 28.0473],
            ['city' => 'Durban', 'lat' => -29.8587, 'lng' => 31.0218],
        ],
        'NG' => [
            ['city' => 'Lagos', 'lat' => 6.5244, 'lng' => 3.3792],
            ['city' => 'Abuja', 'lat' => 9.0765, 'lng' => 7.3986],
        ],
        'EG' => [
            ['city' => 'Cairo', 'lat' => 30.0444, 'lng' => 31.2357],
            ['city' => 'Alexandria', 'lat' => 31.2001, 'lng' => 29.9187],
        ],
        'TR' => [
            ['city' => 'Istanbul', 'lat' => 41.0082, 'lng' => 28.9784],
            ['city' => 'Ankara', 'lat' => 39.9334, 'lng' => 32.8597],
            ['city' => 'Antalya', 'lat' => 36.8969, 'lng' => 30.7133],
        ],
        'PL' => [
            ['city' => 'Warsaw', 'lat' => 52.2297, 'lng' => 21.0122],
            ['city' => 'Krakow', 'lat' => 50.0647, 'lng' => 19.9450],
        ],
        'SE' => [
            ['city' => 'Stockholm', 'lat' => 59.3293, 'lng' => 18.0686],
            ['city' => 'Gothenburg', 'lat' => 57.7089, 'lng' => 11.9746],
        ],
        'NO' => [
            ['city' => 'Oslo', 'lat' => 59.9139, 'lng' => 10.7522],
        ],
        'DK' => [
            ['city' => 'Copenhagen', 'lat' => 55.6761, 'lng' => 12.5683],
        ],
        'FI' => [
            ['city' => 'Helsinki', 'lat' => 60.1699, 'lng' => 24.9384],
        ],
        'CH' => [
            ['city' => 'Zurich', 'lat' => 47.3769, 'lng' => 8.5417],
            ['city' => 'Geneva', 'lat' => 46.2044, 'lng' => 6.1432],
        ],
        'AT' => [
            ['city' => 'Vienna', 'lat' => 48.2082, 'lng' => 16.3738],
        ],
        'GR' => [
            ['city' => 'Athens', 'lat' => 37.9838, 'lng' => 23.7275],
            ['city' => 'Santorini', 'lat' => 36.3932, 'lng' => 25.4615],
        ],
        'PT' => [
            ['city' => 'Lisbon', 'lat' => 38.7223, 'lng' => -9.1393],
            ['city' => 'Porto', 'lat' => 41.1579, 'lng' => -8.6291],
        ],
        'NZ' => [
            ['city' => 'Auckland', 'lat' => -36.8509, 'lng' => 174.7645],
            ['city' => 'Wellington', 'lat' => -41.2866, 'lng' => 174.7756],
        ],
        'AR' => [
            ['city' => 'Buenos Aires', 'lat' => -34.6037, 'lng' => -58.3816],
        ],
        'CL' => [
            ['city' => 'Santiago', 'lat' => -33.4489, 'lng' => -70.6693],
        ],
        'CO' => [
            ['city' => 'Bogota', 'lat' => 4.7110, 'lng' => -74.0721],
            ['city' => 'Medellin', 'lat' => 6.2476, 'lng' => -75.5658],
            ['city' => 'Cartagena', 'lat' => 10.3910, 'lng' => -75.4794],
        ],
        'PE' => [
            ['city' => 'Lima', 'lat' => -12.0464, 'lng' => -77.0428],
            ['city' => 'Cusco', 'lat' => -13.5319, 'lng' => -71.9675],
        ],
    ];

    // ==================== INTERESTS DATABASE ====================

    private $interestsPool = [
        // Social & Entertainment
        'Travel', 'Photography', 'Music', 'Movies', 'Netflix', 'Reading', 'Writing', 'Poetry',
        'Dancing', 'Singing', 'Karaoke', 'Comedy', 'Theater', 'Concerts', 'Festivals', 'Nightlife',
        'Board Games', 'Video Games', 'Anime', 'Manga', 'K-Drama', 'K-Pop', 'J-Pop', 'C-Pop',

        // Food & Drinks
        'Cooking', 'Baking', 'Food', 'Foodie', 'Coffee', 'Tea', 'Wine', 'Cocktails', 'Craft Beer',
        'Sushi', 'Thai Food', 'Italian Food', 'Mexican Food', 'BBQ', 'Vegan', 'Vegetarian',
        'Brunch', 'Fine Dining', 'Street Food', 'Food Photography',

        // Fitness & Sports
        'Fitness', 'Gym', 'Yoga', 'Pilates', 'CrossFit', 'Running', 'Marathon', 'Cycling',
        'Swimming', 'Surfing', 'Skiing', 'Snowboarding', 'Hiking', 'Climbing', 'Camping',
        'Football', 'Soccer', 'Basketball', 'Tennis', 'Golf', 'Volleyball', 'Badminton',
        'Martial Arts', 'Boxing', 'MMA', 'Muay Thai', 'Jiu-Jitsu', 'Taekwondo',
        'Dance Fitness', 'Zumba', 'Spinning', 'HIIT', 'Calisthenics', 'Weightlifting',

        // Outdoor & Nature
        'Nature', 'Beach', 'Mountains', 'Ocean', 'Sunset', 'Stargazing', 'Gardening',
        'Fishing', 'Sailing', 'Kayaking', 'Scuba Diving', 'Snorkeling', 'Skydiving',
        'Road Trips', 'Backpacking', 'Adventure', 'Exploring', 'Wildlife',

        // Creative & Arts
        'Art', 'Painting', 'Drawing', 'Illustration', 'Graphic Design', 'Fashion',
        'Interior Design', 'Architecture', 'Crafts', 'DIY', 'Pottery', 'Calligraphy',
        'Makeup', 'Skincare', 'Hair Styling', 'Tattoos', 'Jewelry Making',

        // Music
        'Guitar', 'Piano', 'Drums', 'Violin', 'DJ', 'EDM', 'Hip-hop', 'R&B', 'Pop',
        'Rock', 'Jazz', 'Classical', 'Country', 'Reggae', 'Latin Music', 'Indie',
        'Lo-fi', 'House Music', 'Techno', 'Trap', 'Soul', 'Funk',

        // Technology & Learning
        'Tech', 'Startups', 'Crypto', 'NFTs', 'Investing', 'Stock Market', 'Finance',
        'Coding', 'AI', 'Science', 'Space', 'Psychology', 'Philosophy', 'History',
        'Languages', 'Self-improvement', 'Meditation', 'Mindfulness', 'Spirituality',

        // Pets & Animals
        'Dogs', 'Cats', 'Pets', 'Animal Lover', 'Horse Riding', 'Bird Watching',

        // Lifestyle
        'Minimalism', 'Sustainability', 'Wellness', 'Mental Health', 'Work-Life Balance',
        'Family', 'Parenting', 'Volunteering', 'Charity', 'Social Causes', 'Environment',

        // Social
        'Networking', 'Socializing', 'Meeting New People', 'Deep Conversations',
        'Late Night Talks', 'Long Walks', 'Cuddling', 'Netflix and Chill'
    ];

    // ==================== BIO & ABOUT TEMPLATES ====================

    private $bioTemplates = [
        // Short & Sweet
        "Just living life one day at a time ✨",
        "Adventure seeker | Coffee addict ☕",
        "Here to meet new people",
        "Love traveling and exploring new places 🌍",
        "Music is my escape 🎵",
        "Foodie at heart 🍕",
        "Looking for genuine connections 💫",
        "Life is short, make it sweet",
        "Work hard, play harder",
        "Dreamer and doer",
        "Just being myself 💕",
        "Positive vibes only ☀️",
        "Making memories",
        "Living my best life",
        "Happiness is a choice",
        "Let's grab coffee sometime ☕",
        "Looking for my partner in crime",
        "Swipe right if you like adventures",
        "Dog lover | Beach person 🐕🏖️",
        "Not here for games",
        "Looking for something real ❤️",
        "Let's see where this goes",
        "New in town, show me around?",
        "Fitness enthusiast 💪",
        "Weekend warrior",
        "Professional overthinker 🤔",
        "Fluent in sarcasm",
        "Probably at the gym 🏋️",
        "Cat person in a dog person's world 🐱",
        "Always up for a good conversation",

        // Fun & Quirky
        "My love language is food 🍜",
        "Can talk about anything for hours",
        "Warning: may randomly burst into song 🎤",
        "Recovering workaholic",
        "Part-time adventurer, full-time dreamer",
        "Swiped right for your dog 🐕",
        "Looking for someone to share fries with 🍟",
        "Professional napper on weekends",
        "Probably thinking about pizza 🍕",
        "6'0 because apparently that matters",
        "Taller in heels 👠",
        "Here because my friends made me",
        "Will send you memes",
        "Gym in the streets, Netflix in the sheets",
        "Ask me about my travels ✈️",
        "Bookworm by day, party animal by night",
        "Coffee first, then we talk ☕",
        "Future cat lady in training",
        "Just a girl looking for her lobster 🦞",
        "Trying to adult, but still love cartoons",

        // Romantic
        "Looking for love in a hopeless place 💔",
        "Ready for something meaningful",
        "Hopeless romantic 💕",
        "Searching for my happily ever after",
        "Let's write our own love story",
        "Looking for the one worth keeping",
        "Old soul looking for my soulmate",
        "Your future favorite notification 📱",
        "Let me be the reason you smile",
        "Ready to fall in love",

        // Confident
        "Main character energy ✨",
        "Confident but still learning",
        "Know my worth, but still swipe right",
        "Self-made and self-loved 💅",
        "Working on my empire",
        "Goals: travel, love, succeed",
        "Not looking, but here anyway",
        "Here to prove dating apps work",
        "Ready to be impressed",
        "Show me something different"
    ];

    private $aboutTemplates = [
        "I'm a {job} who loves {hobby1} and {hobby2}. In my free time, you can find me {activity}. Looking for someone who appreciates the simple things in life.",
        "Passionate about {hobby1}, {hobby2}, and good conversations. I believe in living life to the fullest and making every moment count. Let's create some amazing memories together!",
        "Just a {job} trying to find balance between work and play. I enjoy {hobby1}, {hobby2}, and discovering new restaurants. Looking for someone genuine.",
        "Originally from {city}, now exploring life one adventure at a time. Love {hobby1}, {hobby2}, and trying new things. Let's connect!",
        "I'm all about good vibes, great food, and meaningful connections. When I'm not working as a {job}, I'm probably {activity}.",
        "{hobby1} enthusiast | {hobby2} lover | Always looking for the next adventure. Life's too short for boring conversations.",
        "Here to meet interesting people and see where things go. I'm passionate about {hobby1} and {hobby2}. Coffee dates are my favorite!",
        "Work: {job}. Play: {hobby1}, {hobby2}. Goal: Find someone who laughs at my jokes (even the bad ones).",
        "My friends would describe me as someone who loves {hobby1} and never says no to {hobby2}. I'm a {job} by profession but an adventurer at heart.",
        "Love spending my weekends {activity} or exploring new places. I'm into {hobby1}, {hobby2}, and meeting new people. Let's chat!",
        "A little bit of everything: {hobby1}, {hobby2}, and lots of {activity}. Looking for someone who doesn't take life too seriously.",
        "By day I'm a {job}, by night I'm probably {activity}. I love {hobby1} and {hobby2}. Let's make some memories!",
        "Simple pleasures: {hobby1}, {hobby2}, good food, and great company. I work as a {job} and love what I do.",
        "Currently obsessed with {hobby1} and {hobby2}. When I'm not working, I'm usually {activity}. Tell me your favorite travel story!",
        "Life motto: Work hard, {hobby1} harder. I'm a {job} who believes in balance. Let's get to know each other over {hobby2}!",
        "Just moved to {city} and loving it! I'm into {hobby1}, {hobby2}, and exploring everything this city has to offer.",
        "Curious soul always looking for the next great story. I work in {job}, but my heart belongs to {hobby1} and {hobby2}.",
        "Looking for genuine connections, not just swipes. I'm passionate about {hobby1}, love {hobby2}, and enjoy {activity}.",
        "Half {hobby1} addict, half {hobby2} enthusiast, 100% looking for my person. I work as a {job} and love every minute of it.",
        "Three things about me: I'm obsessed with {hobby1}, I could talk about {hobby2} for hours, and I'm always down for {activity}."
    ];

    private $jobs = [
        // Tech
        'software engineer', 'web developer', 'mobile developer', 'data scientist', 'product manager',
        'UI/UX designer', 'DevOps engineer', 'cybersecurity analyst', 'AI researcher', 'blockchain developer',
        'tech lead', 'CTO', 'startup founder', 'IT consultant', 'systems architect',

        // Creative
        'graphic designer', 'photographer', 'videographer', 'content creator', 'social media manager',
        'copywriter', 'brand strategist', 'art director', 'animator', 'illustrator',
        'fashion designer', 'interior designer', 'architect', 'filmmaker', 'music producer',

        // Business
        'marketing manager', 'sales executive', 'business analyst', 'consultant', 'entrepreneur',
        'accountant', 'financial analyst', 'investment banker', 'project manager', 'HR manager',
        'real estate agent', 'recruiter', 'operations manager', 'CEO', 'business owner',

        // Healthcare
        'doctor', 'nurse', 'dentist', 'pharmacist', 'physical therapist',
        'psychologist', 'nutritionist', 'personal trainer', 'yoga instructor', 'wellness coach',

        // Education
        'teacher', 'professor', 'tutor', 'researcher', 'academic',
        'education consultant', 'school counselor', 'librarian', 'curriculum developer',

        // Service & Hospitality
        'chef', 'barista', 'bartender', 'hotel manager', 'flight attendant',
        'tour guide', 'event planner', 'wedding coordinator', 'restaurant owner',

        // Legal & Government
        'lawyer', 'paralegal', 'judge', 'policy analyst', 'diplomat',
        'civil servant', 'police officer', 'firefighter',

        // Arts & Entertainment
        'musician', 'singer', 'actor', 'model', 'influencer',
        'streamer', 'YouTuber', 'podcaster', 'writer', 'journalist',

        // Other
        'pilot', 'engineer', 'scientist', 'veterinarian', 'electrician',
        'mechanic', 'carpenter', 'athlete', 'coach', 'translator'
    ];

    private $activities = [
        'exploring coffee shops', 'hiking trails', 'watching Netflix', 'at the gym',
        'trying new restaurants', 'reading a book', 'playing video games', 'traveling somewhere new',
        'cooking up something delicious', 'hanging out with friends', 'at a concert', 'walking my dog',
        'binge-watching series', 'learning a new language', 'practicing yoga', 'working on side projects',
        'exploring the city', 'taking photos', 'listening to podcasts', 'jamming to music',
        'planning my next trip', 'experimenting in the kitchen', 'at a museum', 'at the beach',
        'hitting the slopes', 'diving into a new book', 'working out', 'meditating',
        'writing in my journal', 'discovering new music', 'playing sports', 'volunteering'
    ];

    // ==================== MAIN RUN METHOD ====================

    public function run()
    {
        $totalUsers = 1000; // Generate 1000 users

        $users = [];
        $userRoles = [];
        $now = now();

        // Distribution: 58% female, 38% male, 4% other
        $genderDistribution = [];
        for ($i = 0; $i < $totalUsers; $i++) {
            $rand = mt_rand(1, 100);
            if ($rand <= 58) $genderDistribution[] = 2; // female
            elseif ($rand <= 96) $genderDistribution[] = 1; // male
            else $genderDistribution[] = 3; // other
        }

        // Country distribution with weights
        $countryWeights = [
            'VN' => 120, 'US' => 80, 'JP' => 50, 'KR' => 50, 'TH' => 45, 'PH' => 45,
            'SG' => 30, 'MY' => 30, 'ID' => 35, 'TW' => 25, 'HK' => 20,
            'GB' => 40, 'FR' => 30, 'DE' => 30, 'AU' => 35, 'CA' => 30,
            'BR' => 25, 'MX' => 25, 'AE' => 20, 'IN' => 50,
            'RU' => 20, 'ES' => 25, 'IT' => 25, 'NL' => 15,
            'ZA' => 15, 'NG' => 15, 'EG' => 10, 'TR' => 20,
            'PL' => 15, 'SE' => 10, 'NO' => 8, 'DK' => 8, 'FI' => 8,
            'CH' => 10, 'AT' => 8, 'GR' => 12, 'PT' => 12,
            'NZ' => 12, 'AR' => 10, 'CL' => 8, 'CO' => 15, 'PE' => 10
        ];

        $countryPool = [];
        foreach ($countryWeights as $country => $weight) {
            $countryPool = array_merge($countryPool, array_fill(0, $weight, $country));
        }

        $this->command->info("Generating {$totalUsers} mock users...");
        $progressBar = $this->command->getOutput()->createProgressBar($totalUsers);
        $progressBar->start();

        for ($i = 0; $i < $totalUsers; $i++) {
            $gender = $genderDistribution[$i];
            $country = $countryPool[array_rand($countryPool)];
            $location = $this->locations[$country][array_rand($this->locations[$country])];

            // Add random offset to coordinates (within ~15km)
            $lat = $location['lat'] + (mt_rand(-150, 150) / 1000);
            $lng = $location['lng'] + (mt_rand(-150, 150) / 1000);

            // Determine name region based on country
            $nameRegion = $this->getNameRegion($country);

            // Generate name based on gender
            $namePool = $gender == 1 ? $this->maleNames : ($gender == 2 ? $this->femaleNames :
                array_merge($this->maleNames[$nameRegion] ?? [], $this->femaleNames[$nameRegion] ?? []));

            if ($gender != 3) {
                $firstName = $namePool[$nameRegion][array_rand($namePool[$nameRegion])];
            } else {
                $firstName = $namePool[array_rand($namePool)];
            }

            $lastNamePool = $this->lastNames[$nameRegion] ?? $this->lastNames['western'];
            $lastName = $lastNamePool[array_rand($lastNamePool)];
            $fullName = $firstName . ' ' . $lastName;

            // Generate unique username
            $usernameStyles = [
                strtolower($firstName) . '_' . mt_rand(100, 9999),
                strtolower($firstName) . mt_rand(10, 999),
                strtolower($firstName) . '.' . strtolower(substr($lastName, 0, 3)) . mt_rand(10, 99),
                strtolower(substr($firstName, 0, 1) . $lastName) . mt_rand(10, 999),
                'the' . strtolower($firstName) . mt_rand(10, 99),
                strtolower($firstName) . '_official' . mt_rand(1, 99),
                'its' . strtolower($firstName) . mt_rand(10, 999),
                strtolower($firstName) . 'xx' . mt_rand(10, 99),
            ];
            $username = $usernameStyles[array_rand($usernameStyles)];

            // Generate email (identity)
            $emailDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'icloud.com', 'outlook.com', 'mail.com'];
            $identity = strtolower(str_replace(' ', '', $firstName)) . mt_rand(100, 9999) . '@' . $emailDomains[array_rand($emailDomains)];

            // Age distribution (18-55, weighted towards 22-32)
            $age = $this->generateAge();

            // Random interests (3-8)
            $numInterests = mt_rand(3, 8);
            $shuffledInterests = $this->interestsPool;
            shuffle($shuffledInterests);
            $interests = array_slice($shuffledInterests, 0, $numInterests);
            $interestsStr = implode(',', $interests);

            // Bio and About
            $bio = $this->bioTemplates[array_rand($this->bioTemplates)];
            $about = $this->generateAbout($location['city']);

            // Language based on country
            $language = $this->getLanguageByCountry($country);

            // Verification status (8% verified, 12% pending, 80% not verified)
            $verifiedRand = mt_rand(1, 100);
            $isVerified = $verifiedRand <= 8 ? 2 : ($verifiedRand <= 20 ? 1 : 0);

            // Can go live (25% approved, 15% pending, 60% no)
            $liveRand = mt_rand(1, 100);
            $canGoLive = $liveRand <= 25 ? 2 : ($liveRand <= 40 ? 1 : 0);

            // Followers/Following (realistic distribution)
            $followers = $this->generateFollowerCount();
            $following = mt_rand(5, min(800, $followers + 300));

            // Wallet and collected (varies)
            $wallet = $this->generateWallet();
            $totalCollected = mt_rand(200, 15000);

            // Gender preference
            $genderPref = $this->generateGenderPreference($gender);

            // Age preference
            $ageMin = max(18, $age - mt_rand(3, 12));
            $ageMax = min(65, $age + mt_rand(3, 15));

            // Social media (30% have instagram, 15% have youtube, 10% have facebook)
            $instagram = mt_rand(1, 100) <= 30 ? '@' . strtolower($firstName) . '_' . mt_rand(100, 9999) : null;
            $youtube = mt_rand(1, 100) <= 15 ? 'youtube.com/@' . strtolower($firstName) . mt_rand(100, 999) : null;
            $facebook = mt_rand(1, 100) <= 10 ? strtolower($firstName) . '.' . strtolower($lastName) . '.' . mt_rand(10, 99) : null;

            // Created at (random date in last 12 months)
            $createdAt = $now->copy()->subDays(mt_rand(1, 365))->subHours(mt_rand(0, 23))->subMinutes(mt_rand(0, 59));

            // Daily swipes
            $dailySwipes = mt_rand(0, 50);
            $lastSwipeDate = mt_rand(1, 100) <= 70 ? $now->copy()->subDays(mt_rand(0, 7))->toDateString() : null;

            $users[] = [
                'is_block' => mt_rand(1, 100) <= 2 ? 1 : 0, // 2% blocked
                'gender' => $gender,
                'interests' => $interestsStr,
                'age' => $age,
                'identity' => $identity,
                'username' => $username,
                'fullname' => $fullName,
                'instagram' => $instagram,
                'youtube' => $youtube,
                'facebook' => $facebook,
                'bio' => $bio,
                'about' => $about,
                'lattitude' => (string)round($lat, 6),
                'longitude' => (string)round($lng, 6),
                'country' => $country,
                'language' => $language,
                'login_type' => $this->generateLoginType(),
                'device_token' => null,
                'wallet' => $wallet,
                'daily_swipes' => $dailySwipes,
                'last_swipe_date' => $lastSwipeDate,
                'total_collected' => $totalCollected,
                'total_streams' => $canGoLive == 2 ? mt_rand(0, 200) : 0,
                'device_type' => mt_rand(1, 2),
                'is_notification' => mt_rand(1, 100) <= 85 ? 1 : 0,
                'is_verified' => $isVerified,
                'show_on_map' => mt_rand(1, 100) <= 75 ? 1 : 0,
                'anonymous' => mt_rand(1, 100) <= 8 ? 1 : 0,
                'is_video_call' => mt_rand(1, 100) <= 85 ? 1 : 0,
                'can_go_live' => $canGoLive,
                'swipe_tutorial' => 1,
                'is_live_now' => 0,
                'is_fake' => 1,
                'password' => bcrypt('password123'),
                'following' => $following,
                'followers' => $followers,
                'gender_preferred' => $genderPref,
                'age_preferred_min' => $ageMin,
                'age_preferred_max' => $ageMax,
                'created_at' => $createdAt,
                'update_at' => $createdAt,
            ];

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine();

        // Insert users in chunks
        $this->command->info('Inserting users into database...');
        foreach (array_chunk($users, 100) as $chunk) {
            DB::table('users')->insert($chunk);
        }

        // Get the inserted user IDs
        $insertedIds = DB::table('users')
            ->where('is_fake', 1)
            ->orderBy('id', 'desc')
            ->limit($totalUsers)
            ->pluck('id');

        // Create user_roles for each user
        $this->command->info('Creating user roles...');
        foreach ($insertedIds as $userId) {
            $userRoles[] = [
                'user_id' => $userId,
                'role_type' => 'normal',
                'granted_at' => $now,
                'expires_at' => null,
                'is_active' => true,
                'granted_by_admin_id' => null,
            ];
        }

        // Insert user roles in chunks
        foreach (array_chunk($userRoles, 100) as $chunk) {
            DB::table('user_roles')->insert($chunk);
        }

        $this->command->info("✅ Created {$totalUsers} mock users successfully!");
        $this->command->info("📊 Distribution:");
        $this->command->info("   - Female: ~58%");
        $this->command->info("   - Male: ~38%");
        $this->command->info("   - Other: ~4%");
        $this->command->info("   - Countries: 40+ countries");
        $this->command->info("   - Cities: 150+ cities");
    }

    // ==================== HELPER METHODS ====================

    private function getNameRegion($country)
    {
        $regions = [
            'VN' => 'asian', 'JP' => 'asian', 'KR' => 'asian', 'TH' => 'asian',
            'PH' => 'asian', 'SG' => 'asian', 'MY' => 'asian', 'ID' => 'asian',
            'TW' => 'asian', 'HK' => 'asian', 'IN' => 'indian',
            'BR' => 'latin', 'MX' => 'latin', 'AR' => 'latin', 'CL' => 'latin',
            'CO' => 'latin', 'PE' => 'latin', 'ES' => 'latin', 'PT' => 'latin',
            'AE' => 'arabic', 'EG' => 'arabic', 'TR' => 'arabic',
            'RU' => 'russian',
            'ZA' => 'african', 'NG' => 'african',
            'US' => 'western', 'GB' => 'western', 'FR' => 'western', 'DE' => 'western',
            'AU' => 'western', 'CA' => 'western', 'NL' => 'western', 'IT' => 'western',
            'PL' => 'western', 'SE' => 'western', 'NO' => 'western', 'DK' => 'western',
            'FI' => 'western', 'CH' => 'western', 'AT' => 'western', 'GR' => 'western',
            'NZ' => 'western'
        ];
        return $regions[$country] ?? 'western';
    }

    private function getLanguageByCountry($country)
    {
        $languages = [
            'VN' => 'vi', 'US' => 'en', 'JP' => 'ja', 'KR' => 'ko', 'TH' => 'th',
            'PH' => 'en', 'SG' => 'en', 'MY' => 'en', 'ID' => 'id', 'TW' => 'zh',
            'HK' => 'zh', 'GB' => 'en', 'FR' => 'fr', 'DE' => 'de', 'AU' => 'en',
            'CA' => 'en', 'BR' => 'pt', 'MX' => 'es', 'AE' => 'ar', 'IN' => 'hi',
            'RU' => 'ru', 'ES' => 'es', 'IT' => 'it', 'NL' => 'nl',
            'ZA' => 'en', 'NG' => 'en', 'EG' => 'ar', 'TR' => 'tr',
            'PL' => 'pl', 'SE' => 'sv', 'NO' => 'no', 'DK' => 'da', 'FI' => 'fi',
            'CH' => 'de', 'AT' => 'de', 'GR' => 'el', 'PT' => 'pt',
            'NZ' => 'en', 'AR' => 'es', 'CL' => 'es', 'CO' => 'es', 'PE' => 'es'
        ];
        return $languages[$country] ?? 'en';
    }

    private function generateAge()
    {
        $rand = mt_rand(1, 100);
        if ($rand <= 5) return mt_rand(18, 19);       // 5% are 18-19
        if ($rand <= 25) return mt_rand(20, 24);      // 20% are 20-24
        if ($rand <= 55) return mt_rand(25, 29);      // 30% are 25-29
        if ($rand <= 75) return mt_rand(30, 34);      // 20% are 30-34
        if ($rand <= 88) return mt_rand(35, 39);      // 13% are 35-39
        if ($rand <= 95) return mt_rand(40, 45);      // 7% are 40-45
        return mt_rand(46, 55);                        // 5% are 46-55
    }

    private function generateFollowerCount()
    {
        $rand = mt_rand(1, 100);
        if ($rand <= 40) return mt_rand(0, 50);        // 40% have 0-50
        if ($rand <= 65) return mt_rand(51, 200);      // 25% have 51-200
        if ($rand <= 80) return mt_rand(201, 500);     // 15% have 201-500
        if ($rand <= 90) return mt_rand(501, 2000);    // 10% have 501-2000
        if ($rand <= 97) return mt_rand(2001, 10000);  // 7% have 2001-10000
        return mt_rand(10001, 100000);                  // 3% have 10001-100000
    }

    private function generateWallet()
    {
        $rand = mt_rand(1, 100);
        if ($rand <= 50) return mt_rand(0, 100);       // 50% have 0-100
        if ($rand <= 75) return mt_rand(101, 500);     // 25% have 101-500
        if ($rand <= 90) return mt_rand(501, 2000);    // 15% have 501-2000
        if ($rand <= 97) return mt_rand(2001, 10000);  // 7% have 2001-10000
        return mt_rand(10001, 50000);                   // 3% have 10001-50000
    }

    private function generateLoginType()
    {
        $rand = mt_rand(1, 100);
        if ($rand <= 45) return 1; // Google (45%)
        if ($rand <= 75) return 2; // Apple (30%)
        if ($rand <= 90) return 3; // Phone (15%)
        return 4;                   // Email (10%)
    }

    private function generateGenderPreference($userGender)
    {
        // Realistic preference distribution
        if ($userGender == 1) { // Male
            $rand = mt_rand(1, 100);
            if ($rand <= 85) return 2; // Looking for female
            if ($rand <= 95) return 3; // Looking for both
            return 1;                   // Looking for male
        } elseif ($userGender == 2) { // Female
            $rand = mt_rand(1, 100);
            if ($rand <= 80) return 1; // Looking for male
            if ($rand <= 95) return 3; // Looking for both
            return 2;                   // Looking for female
        } else { // Other
            return 3; // Looking for both
        }
    }

    private function generateAbout($city)
    {
        $template = $this->aboutTemplates[array_rand($this->aboutTemplates)];

        $shuffledInterests = $this->interestsPool;
        shuffle($shuffledInterests);

        $hobby1 = $shuffledInterests[0];
        $hobby2 = $shuffledInterests[1];
        $job = $this->jobs[array_rand($this->jobs)];
        $activity = $this->activities[array_rand($this->activities)];

        return str_replace(
            ['{hobby1}', '{hobby2}', '{job}', '{activity}', '{city}'],
            [$hobby1, $hobby2, $job, $activity, $city],
            $template
        );
    }
}
