import openpyxl

file_path = r"C:\Users\91767\Downloads\AP SCHOOLS LIST GOVT & AIDED.xlsx"
wb = openpyxl.load_workbook(file_path, read_only=True)
sheet = wb.active

total_rows = 0
districts = set()
vetlapalem_rows = []

for row in sheet.iter_rows(values_only=True):
    if not row or row[0] == 'S.No.':
        continue
    total_rows += 1
    dist = row[1]
    mandal = row[2]
    code = row[3]
    school_name = row[4]
    
    if dist:
        districts.add(str(dist).strip())
    if school_name and 'VETLAPALEM' in str(school_name).upper():
        vetlapalem_rows.append((dist, mandal, code, school_name))

print(f"Total schools in Excel: {total_rows}")
print(f"Total distinct districts ({len(districts)}):")
for d in sorted(districts):
    print(f"  - {d}")

print("\nVetlapalem matches in Excel:")
for r in vetlapalem_rows:
    print(" ", r)
