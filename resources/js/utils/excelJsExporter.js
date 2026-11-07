// resources/js/utils/excelJsExporter.js
import ExcelJS from 'exceljs';

/**
 * Modernized Export Utility for CCplus datasets using ExcelJS.
 * Handles template layout preservation, data sanitization, column auto-fitting, 
 * and explicit hidden gridline view configurations.
 */
export async function exportDatasetToExcel({
  datasetKey,
  title,
  consoKey,
  capDataset,
  exportDataRows,
  filterOptions,
  exportInstScope,
  exportPlatScope,
  exportStatus
}) {
  let fileName = "CCplus_" + consoKey;

  // 1. Determine dynamic file naming constraints
  if (datasetKey === 'connections') {
    if (filterOptions && filterOptions.results && filterOptions.results['value'] && filterOptions.results['value'].length <= 1) {
      let _val = (filterOptions.results['value'].length == 1) ? "_" + filterOptions.results['value'] : "";
      fileName += _val.replaceAll(' ', '');
    } else {
      fileName += "_SomeResults";
    }
  } else {
    fileName += (exportInstScope != '') ? exportInstScope : '';
    fileName += (exportPlatScope != '') ? exportPlatScope : '';
    fileName += (exportStatus != '') ? exportStatus : '';
  }

  let sheet_name = (title == 'Credentials') ? 'Credentials' : title + 's';
  const workbook = new ExcelJS.Workbook();

    // 2. Process blueprints / instruction template tabs natively
  if (datasetKey == 'audit') {
    fileName += "_COUNTERAudit.xlsx";
    sheet_name = 'Credentials Audit';
  } else {
    let publicPath = '/templates/' + datasetKey + '.xlsx';
    try {
      const response = await fetch(publicPath);
      if (!response.ok) throw new Error('Network response was not ok');
      const arrayBuffer = await response.arrayBuffer();
      
      // Load the template layout cleanly into a temporary workspace
      const templateBook = new ExcelJS.Workbook();
      await templateBook.xlsx.load(arrayBuffer);
      
      const sourceSheet = templateBook.getWorksheet('HowToImport');
      if (!sourceSheet) throw new Error(`HowToImport Sheet not found for: ${datasetKey}`);
      
      // Clone the template worksheet structure into our output workbook
      const howToSheet = workbook.addWorksheet('How to Import');
      
      // Explicitly hide gridlines on the instruction tab
      howToSheet.views = [{ showGridLines: false }];

      // ─── FIX: PRESERVE ORIGINAL COLUMN WIDTHS ───
      if (sourceSheet.columns) {
        howToSheet.columns = sourceSheet.columns.map(col => ({
          width: col.width // Carries over the exact width you set in Excel
        }));
      }

      // Copy values, styles, and dimensions row by row
      sourceSheet.eachRow({ includeEmpty: true }, (row, rowNumber) => {
        const newRow = howToSheet.getRow(rowNumber);
        newRow.height = row.height;
        
        row.eachCell({ includeEmpty: true }, (cell, colNumber) => {
          const newCell = newRow.getCell(colNumber);
          newCell.value = cell.value;
          if (cell.style) {
            newCell.style = JSON.parse(JSON.stringify(cell.style)); // Deep clone stylistic tokens safely
          }
        });
      });
    } catch (error) {
      console.error("Error during template file layout replication: ", error);
    }
    fileName += "_" + capDataset + ".xlsx";
  }


  // 3. Generate dynamic data tab
  const itemsSheet = workbook.addWorksheet(sheet_name);
  
  // Keep gridlines visible on the data sheet for readability, or set to false if desired
  itemsSheet.views = [{ showGridLines: true }];

  // Sanitize input rows and handle header layouts cleanly
  const safeDataRows = Array.isArray(exportDataRows) ? exportDataRows : [];
  
  if (safeDataRows.length > 0) {
    // Extract column keys from the computed map structure
    const headers = Object.keys(safeDataRows[0]);
    
    // Configure columns array with header names
    itemsSheet.columns = headers.map(header => ({
      header: header,
      key: header
    }));

    // Append rows safely
    safeDataRows.forEach(row => {
      const rowData = {};
      headers.forEach(key => {
        // Strip null values into empty strings to stay safe against Excel layout recovery errors
        rowData[key] = (row[key] === null || row[key] === undefined) ? "" : row[key];
      });
      itemsSheet.addRow(rowData);
    });

    // ─── SAFE AUTO-SIZING BLOCK ───
    try {
      itemsSheet.columns.forEach(column => {
        let maxCharacterLength = column.header ? String(column.header).length : 0;
        
        column.eachCell({ includeEmpty: true }, (cell) => {
          if (cell && cell.value !== undefined && cell.value !== null) {
            const cellLength = String(cell.value).length;
            if (cellLength > maxCharacterLength) {
              maxCharacterLength = cellLength;
            }
          }
        });
        
        // Apply width padding
        column.width = maxCharacterLength < 10 ? 12 : maxCharacterLength + 3;
      });
    } catch (autosizeError) {
      console.warn("Auto-sizing column calculation bypassed safely:", autosizeError);
    }
    // ─── END OF SAFE AUTO-SIZING BLOCK ───
  }

  // 4. Generate native stream buffer and trigger SPA download pipeline
  try {
    const buffer = await workbook.xlsx.writeBuffer();
    const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const url = window.URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = fileName;
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
    window.URL.revokeObjectURL(url);
  } catch (writeError) {
    console.error("Failed executing workbook compilation or download stream:", writeError);
  }
}
