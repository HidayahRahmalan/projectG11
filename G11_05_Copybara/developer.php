<?php
$group_members = [
    [
        'name' => 'AFIFAH SYAZA HUDA BINTI AHMAD',
        'image' => 'images/afifah.jpg', 
        'resume' => 'resumes/afifah_resume.pdf',
    ],
    [
        'name' => 'NUR FATHEHAH BINTI MOHD ARIS',
        'image' => 'images/fathehah.jpg',
        'resume' => 'resumes/fathehah_resume.pdf',
    ],
    [
        'name' => 'NUR AMIERA BADRIESYIA BINTI ZAINAL',
        'image' => 'images/miera.jpg',
        'resume' => 'resumes/nuramiera_resume.pdf',
    ],
    [
        'name' => 'NUR ELESA AQILAH BINTI ABDUL RAHMAN',
        'image' => 'images/elesaaqilah.jpg',
        'resume' => 'resumes/elesa_resume.pdf',
    ],
    [
        'name' => 'NUR LYANA ATHIRAH BINTI MOHD FADLY',
        'image' => 'images/lyana.jpg',
        'resume' => 'resumes/lyana_resume.pdf',
    ],
];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Group Members Projects</title>
    <link rel="stylesheet" href="mmdb/style.css">
    <style>
        .custom-members-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 32px;
            margin-top: 40px;
        }
        .members-row {
            display: flex;
            gap: 32px;
            justify-content: center;
            width: 100%;
        }
        .custom-member-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(44,62,80,0.10);
            padding: 24px 16px;
            width: 220px;
            text-align: center;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .custom-member-card:hover {
            box-shadow: 0 6px 24px rgba(52,152,219,0.20);
            transform: translateY(-5px) scale(1.03);
        }
        .custom-member-card img {
            width: 160px;
            height: 160px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 12px;
            border: 3px solid #3498db;
            background: #f7f9fc;
        }
        .custom-member-card .name {
            font-weight: 600;
            font-size: 1.1em;
            color: #2c3e50;
            margin-bottom: 10px;
            min-height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .custom-member-card a {
            text-decoration: none;
        }
        .custom-member-card a.resume-btn {
            display: inline-block;
            margin-top: 6px;
            padding: 7px 20px;
            background: #e74c3c;
            color: #fff;
            border-radius: 6px;
            font-size: 0.97em;
            transition: background 0.2s;
        }
        .custom-member-card a.resume-btn:hover {
            background: #c0392b;
        }
        .custom-title {
            text-align: center;
            margin-top: 32px;
            margin-bottom: 0;
            font-size: 2em;
            color: #2c3e50;
            letter-spacing: 1px;
        }
        @media (max-width: 900px) {
            .members-row {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="custom-title">Our Group Members </h2>
        <div class="custom-members-container">
            <div class="members-row">
                <?php for ($i=0; $i<3; $i++): $member = $group_members[$i]; ?>
                <div class="custom-member-card">
                    <a href="<?php echo htmlspecialchars($member['resume']); ?>" target="_blank" title="View Resume">
                        <img src="<?php echo htmlspecialchars($member['image']); ?>" alt="<?php echo htmlspecialchars($member['name']); ?> Project Image">
                    </a>
                    <div class="name"><?php echo htmlspecialchars($member['name']); ?></div>
                    <a href="<?php echo htmlspecialchars($member['resume']); ?>" target="_blank" class="resume-btn">View Resume</a>
                </div>
                <?php endfor; ?>
            </div>
            <div class="members-row">
                <?php for ($i=3; $i<5; $i++): $member = $group_members[$i]; ?>
                <div class="custom-member-card">
                    <a href="<?php echo htmlspecialchars($member['resume']); ?>" target="_blank" title="View Resume">
                        <img src="<?php echo htmlspecialchars($member['image']); ?>" alt="<?php echo htmlspecialchars($member['name']); ?> Project Image">
                    </a>
                    <div class="name"><?php echo htmlspecialchars($member['name']); ?></div>
                    <a href="<?php echo htmlspecialchars($member['resume']); ?>" target="_blank" class="resume-btn">View Resume</a>
                </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</body>
</html>