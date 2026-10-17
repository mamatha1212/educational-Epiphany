import openpyxl
import sqlite3
import re

excel_path = r"C:\Users\91767\Downloads\AP SCHOOLS LIST GOVT & AIDED.xlsx"
db_path = r"c:\Users\91767\Desktop\eemt\eemt2027_app\database\eemt2027.sqlite"

def normalize_text(text):
    if not text:
        return ""
    # uppercase, remove punctuation and extra spaces
    text = re.sub(r'[^A-Z0-9]', ' ', str(text).upper())
    return ' '.join(text.split())

def extract_base_name(name):
    # Strip common prefixes/suffixes for cleaner matching
    name = normalize_text(name)
    tokens = [t for t in name.split() if t not in ('GOVT', 'ZPHS', 'MPUPS', 'MPPS', 'MPL', 'HS', 'PS', 'UPS', 'SCHOOL', 'WARD', 'NO', 'P')]
    return ' '.join(tokens)

wb = openpyxl.load_workbook(excel_path, read_only=True)
sheet = wb.active

# Load excel schools
# Key by normalized mandal: list of (school_name, udise_code, norm_name, base_name)
excel_by_mandal = {}
total_excel = 0

for row in sheet.iter_rows(values_only=True):
    if not row or row[0] == 'S.No.':
        continue
    total_excel += 1
    mandal_raw = str(row[2] or '').strip().upper()
    code_raw = str(row[3] or '').strip()
    school_raw = str(row[4] or '').strip()
    
    # Clean code: if ends with .0 from float
    if code_raw.endswith('.0'):
        code_raw = code_raw[:-2]
    
    norm_mandal = normalize_text(mandal_raw)
    norm_school = normalize_text(school_raw)
    base_school = extract_base_name(school_raw)
    
    if norm_mandal not in excel_by_mandal:
        excel_by_mandal[norm_mandal] = []
    excel_by_mandal[norm_mandal].append((school_raw, code_raw, norm_school, base_school))

print(f"Loaded {total_excel} schools from Excel into {len(excel_by_mandal)} mandals.")

# Connect DB
conn = sqlite3.connect(db_path)
cursor = conn.cursor()
cursor.execute("SELECT id, district, mandal, school_name FROM schools_master")
db_schools = cursor.fetchall()
conn.close()

exact_matches = 0
base_matches = 0
unmatched = []

for s_id, dist, mandal, s_name in db_schools:
    # mandal in DB has format e.g. "ANAKAPALLI-281333" -> extract name "ANAKAPALLI"
    m_clean = re.sub(r'-\d+$', '', mandal).strip().upper()
    norm_m = normalize_text(m_clean)
    norm_s = normalize_text(s_name)
    base_s = extract_base_name(s_name)
    
    candidates = excel_by_mandal.get(norm_m, [])
    
    # 1. Exact normalized match
    match = None
    for cand_raw, cand_code, cand_norm, cand_base in candidates:
        if cand_norm == norm_s:
            match = cand_code
            exact_matches += 1
            break
            
    # 2. Try base name match if exact didn't match
    if not match and base_s and len(base_s) > 3:
        for cand_raw, cand_code, cand_norm, cand_base in candidates:
            if cand_base == base_s or (len(base_s) > 5 and base_s in cand_norm) or (len(cand_base) > 5 and cand_base in norm_s):
                match = cand_code
                base_matches += 1
                break
                
    if not match:
        unmatched.append((dist, mandal, s_name))

print(f"Total DB schools: {len(db_schools)}")
print(f"Exact Matches: {exact_matches}")
print(f"Base/Fuzzy Matches: {base_matches}")
print(f"Total Matched: {exact_matches + base_matches} / {len(db_schools)} ({(exact_matches + base_matches)/len(db_schools)*100:.1f}%)")
print(f"Unmatched: {len(unmatched)}")
if unmatched:
    print("\nSample 10 Unmatched:")
    for u in unmatched[:10]:
        print(" ", u)
