<?php
/**
 * EEMT 2027 - Central Landing Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = getDB();

// Quick statistics
$schoolCountStmt = $pdo->query("SELECT COUNT(*) FROM school_registrations_2027");
$registeredSchoolsCount = (int)$schoolCountStmt->fetchColumn();

$masterCountStmt = $pdo->query("SELECT COUNT(*) FROM schools_master");
$totalMasterSchools = (int)$masterCountStmt->fetchColumn();

$districtsCountStmt = $pdo->query("SELECT COUNT(DISTINCT district) FROM schools_master");
$totalDistrictsCount = (int)$districtsCountStmt->fetchColumn();

$pageTitle = "EEMT 2027 - Educational Epiphany Merit Test | AP Govt Schools";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container" style="max-width: 1100px;">
    <!-- Hero Banner -->
    <div class="card" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%); color: white; padding: 3rem 2.5rem; border-radius: 20px; box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.4); margin-bottom: 2.5rem; text-align: center; position: relative; overflow: hidden;">
        <div style="position: absolute; top: -50px; right: -50px; width: 220px; height: 220px; background: rgba(59, 130, 246, 0.15); border-radius: 50%; filter: blur(40px);"></div>
        <div style="position: absolute; bottom: -50px; left: -50px; width: 220px; height: 220px; background: rgba(16, 185, 129, 0.15); border-radius: 50%; filter: blur(40px);"></div>

        <div style="position: relative; z-index: 2;">
            <div style="margin-bottom: 1.25rem;">
                <img src="assets/images/logo.png" alt="EEMT Logo" style="height: 85px; width: auto; background: white; padding: 6px; border-radius: 18px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); display: inline-block;" onerror="this.src='assets/images/logo.jpeg';">
            </div>
            <span class="badge" style="background: rgba(255, 255, 255, 0.15); color: #93c5fd; font-size: 0.85rem; padding: 0.4rem 1rem; border: 1px solid rgba(255, 255, 255, 0.2); margin-bottom: 1.25rem;">
                Official Prelims Registration &bull; Andhra Pradesh Government Schools
            </span>

            <h1 style="font-size: 2.75rem; font-weight: 800; color: #ffffff; line-height: 1.2; margin-bottom: 1rem; letter-spacing: -0.5px;">
                EEMT 2027 State-Level Prelims Examination
            </h1>

            <p style="font-size: 1.15rem; color: #cbd5e1; max-width: 720px; margin: 0 auto 2rem; line-height: 1.6;">
                Welcoming all Headmasters, Principals, and Teachers across <strong><?php echo $totalDistrictsCount; ?> Districts</strong> of Andhra Pradesh. Register your school using your unique school code and download official prelims question papers for your students.
            </p>

            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="register.php" class="btn btn-primary" style="font-size: 1.05rem; padding: 0.9rem 2rem; background: #2563eb; box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.5);">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Register School for EEMT 2027
                </a>
                <a href="prelims_qp.php" class="btn" style="font-size: 1.05rem; padding: 0.9rem 2rem; background: #059669; color: white; box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.5);">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Prelims QP Portal
                </a>
                <a href="school_login.php" class="btn" style="font-size: 1.05rem; padding: 0.9rem 2rem; background: rgba(255, 255, 255, 0.12); color: white; border: 1px solid rgba(255, 255, 255, 0.25);">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                    School Login Portal
                </a>
            </div>
        </div>
    </div>

    <!-- 3 Quick Access Role Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 3rem;">
        <!-- Card 1: School Registration -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #2563eb;">
            <div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: #eff6ff; display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem; color: #2563eb;">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
                <h3 style="font-size: 1.35rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;">New School Registration</h3>
                <p style="color: #64748b; font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    For Headmasters & Teachers of AP Government Schools. Select your District, Mandal, School name, verify your 11-digit UDISE Code, and receive your <strong>EEMTSRC Code (26275XXXXXX)</strong>.
                </p>
            </div>
            <a href="register.php" class="btn btn-primary" style="width: 100%; justify-content: center;">
                Start Registration &rarr;
            </a>
        </div>

        <!-- Card 2: Prelims QP Portal -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #10b981;">
            <div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: #ecfdf5; display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem; color: #10b981;">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                </div>
                <h3 style="font-size: 1.35rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;">Prelims QP Download Portal</h3>
                <p style="color: #64748b; font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    Validation check is mandatory. Enter your school's <strong>1. UDISE Code</strong> and <strong>2. EEMTSRC (Format: 26275XXXXXX)</strong> to access and download official question papers.
                </p>
            </div>
            <a href="prelims_qp.php" class="btn btn-success" style="width: 100%; justify-content: center;">
                Access Prelims QP &rarr;
            </a>
        </div>

        <!-- Card 3: Admin & Coordinator -->
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s, box-shadow 0.2s; border-top: 4px solid #6366f1;">
            <div>
                <div style="width: 48px; height: 48px; border-radius: 12px; background: #eef2ff; display: flex; align-items: center; justify-content: center; margin-bottom: 1.25rem; color: #6366f1;">
                    <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
                <h3 style="font-size: 1.35rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem;">Staff & Coordinator Login</h3>
                <p style="color: #64748b; font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    Authorized state and district coordinators can log in to view real-time registrations, filter schools, export reports, and publish question papers.
                </p>
            </div>
            <a href="staff_login.php" class="btn btn-outline" style="width: 100%; justify-content: center;">
                Coordinator Sign In &rarr;
            </a>
        </div>
    </div>

    <!-- How it works guide -->
    <div class="card" style="margin-bottom: 3rem;">
        <h3 style="font-size: 1.35rem; font-weight: 700; color: #0f172a; margin-bottom: 1.5rem; text-align: center;">How EEMT 2027 Registration Works</h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem;">
            <div style="text-align: center; padding: 1rem;">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: #2563eb; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-weight: 800;">1</div>
                <h4 style="font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">Select Your School</h4>
                <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Choose your AP District & Mandal, then select your registered Government School.</p>
            </div>

            <div style="text-align: center; padding: 1rem;">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: #2563eb; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-weight: 800;">2</div>
                <h4 style="font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">Enter Unique Code</h4>
                <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Enter your school's unique code. The system validates it in real time against state records.</p>
            </div>

            <div style="text-align: center; padding: 1rem;">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: #2563eb; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-weight: 800;">3</div>
                <h4 style="font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">Get Reference ID</h4>
                <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Enter contact person info. An official Reference ID is generated and sent to your email.</p>
            </div>

            <div style="text-align: center; padding: 1rem;">
                <div style="width: 40px; height: 40px; border-radius: 50%; background: #2563eb; color: white; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-weight: 800;">4</div>
                <h4 style="font-weight: 700; color: #1e293b; margin-bottom: 0.35rem;">Download Papers</h4>
                <p style="font-size: 0.85rem; color: #64748b; margin: 0;">Log in anytime with Reference ID + Code to access released Prelims Question Papers.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

