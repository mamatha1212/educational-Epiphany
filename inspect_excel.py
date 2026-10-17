import openpyxl

file_path = r"C:\Users\91767\Downloads\AP SCHOOLS LIST GOVT & AIDED.xlsx"
wb = openpyxl.load_workbook(file_path, read_only=True)
print("Sheet names:", wb.sheetnames)

sheet = wb.active
rows = []
for i, row in enumerate(sheet.iter_rows(values_only=True)):
    rows.append(row)
    if i >= 10:
        break

print("\nFirst 10 rows:")
for r in rows:
    print(r)
