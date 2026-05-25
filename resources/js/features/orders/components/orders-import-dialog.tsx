import { useState, useRef, useCallback } from 'react'
import { Upload, FileSpreadsheet, X, CheckCircle2, AlertCircle } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Progress } from '@/components/ui/progress'
import { Alert, AlertDescription } from '@/components/ui/alert'

const MAX_FILE_SIZE_MB = 10
const ALLOWED_MIME = ['text/csv', 'application/vnd.ms-excel', 'application/csv']

interface ImportResult {
  success: boolean
  message: string
  imported?: number
  duplicates?: number
  invalid?: number
  total?: number
  has_errors?: boolean
  errors?: Array<{
    row: number
    order_number?: string
    reason: string
    details: string
  }>
}

interface OrdersImportDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess?: () => void
}

function validateFile(file: File): string | null {
  const isCSV =
    file.name.toLowerCase().endsWith('.csv') ||
    ALLOWED_MIME.includes(file.type) ||
    file.type === ''
  if (!isCSV) return 'Only CSV files are supported.'
  if (file.size === 0) return 'The selected file is empty.'
  if (file.size > MAX_FILE_SIZE_MB * 1024 * 1024)
    return `File size must be under ${MAX_FILE_SIZE_MB}MB. Selected file is ${(file.size / 1024 / 1024).toFixed(1)}MB.`
  return null
}

export function OrdersImportDialog({
  open,
  onOpenChange,
  onSuccess,
}: OrdersImportDialogProps) {
  const [file, setFile] = useState<File | null>(null)
  const [fileError, setFileError] = useState<string | null>(null)
  const [uploading, setUploading] = useState(false)
  const [progress, setProgress] = useState(0)
  const [isDragging, setIsDragging] = useState(false)
  const [result, setResult] = useState<ImportResult | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const dragCounter = useRef(0)

  const selectFile = useCallback((f: File) => {
    const err = validateFile(f)
    if (err) {
      setFileError(err)
      setFile(null)
    } else {
      setFileError(null)
      setFile(f)
      setResult(null)
    }
  }, [])

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selected = e.target.files?.[0]
    if (selected) selectFile(selected)
    e.target.value = ''
  }

  const handleDragEnter = (e: React.DragEvent) => {
    e.preventDefault()
    dragCounter.current++
    setIsDragging(true)
  }

  const handleDragLeave = (e: React.DragEvent) => {
    e.preventDefault()
    dragCounter.current--
    if (dragCounter.current === 0) setIsDragging(false)
  }

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault()
  }

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault()
    dragCounter.current = 0
    setIsDragging(false)
    const dropped = e.dataTransfer.files[0]
    if (dropped) selectFile(dropped)
  }

  const handleRemoveFile = () => {
    setFile(null)
    setFileError(null)
    setResult(null)
  }

  const handleImport = async () => {
    if (!file) return

    setUploading(true)
    setProgress(0)
    setResult(null)

    const formData = new FormData()
    formData.append('file', file)

    const progressInterval = setInterval(
      () => setProgress((prev) => Math.min(prev + 8, 88)),
      250
    )

    try {
      const response = await fetch('/api/orders/import', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN':
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: formData,
      })

      clearInterval(progressInterval)
      setProgress(100)

      const text = await response.text()
      let data: ImportResult
      try {
        data = JSON.parse(text)
      } catch {
        data = {
          success: false,
          message: `Server error (${response.status}): unexpected response format.`,
        }
      }

      if (response.ok && data.success) {
        setResult(data)
        onSuccess?.()
      } else {
        setResult({
          success: false,
          message: data.message || `Import failed (HTTP ${response.status}).`,
        })
      }
    } catch (error) {
      clearInterval(progressInterval)
      setProgress(0)
      setResult({
        success: false,
        message:
          error instanceof Error
            ? error.message
            : 'An unexpected error occurred. Please try again.',
      })
    } finally {
      setUploading(false)
    }
  }

  const handleReset = () => {
    setFile(null)
    setFileError(null)
    setResult(null)
    setProgress(0)
    setIsDragging(false)
    dragCounter.current = 0
  }

  const handleClose = () => {
    if (uploading) return
    handleReset()
    onOpenChange(false)
  }

  const showDropzone = !file && !result
  const showFileCard = !!file && !result

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className='sm:max-w-[500px]'>
        <DialogHeader>
          <DialogTitle>Import Orders from CSV</DialogTitle>
          <DialogDescription>
            Upload a CSV file containing order data. The file should match the Shopify export format.
          </DialogDescription>
        </DialogHeader>

        <div className='space-y-4'>
          {/* Drop zone */}
          {showDropzone && (
            <div
              onDragEnter={handleDragEnter}
              onDragLeave={handleDragLeave}
              onDragOver={handleDragOver}
              onDrop={handleDrop}
              onClick={() => fileInputRef.current?.click()}
              className={`flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-12 text-center transition-colors cursor-pointer select-none ${
                isDragging
                  ? 'border-primary bg-primary/5'
                  : fileError
                  ? 'border-destructive/50 bg-destructive/5'
                  : 'border-muted-foreground/25 hover:border-muted-foreground/50'
              }`}
            >
              <FileSpreadsheet
                className={`mb-4 h-12 w-12 ${
                  isDragging
                    ? 'text-primary'
                    : fileError
                    ? 'text-destructive'
                    : 'text-muted-foreground'
                }`}
              />
              <div className='mb-4'>
                <p className='text-sm font-medium'>
                  {isDragging ? 'Drop your CSV file here' : 'Drag & drop or click to select'}
                </p>
                <p className='text-xs text-muted-foreground mt-1'>
                  CSV files only • Maximum {MAX_FILE_SIZE_MB}MB
                </p>
              </div>
              <Button
                variant='outline'
                size='sm'
                type='button'
                onClick={(e) => {
                  e.stopPropagation()
                  fileInputRef.current?.click()
                }}
              >
                <Upload className='mr-2 h-4 w-4' />
                Choose File
              </Button>
              <input
                ref={fileInputRef}
                type='file'
                accept='.csv,text/csv'
                onChange={handleFileChange}
                className='hidden'
              />
            </div>
          )}

          {/* File validation error */}
          {fileError && !file && (
            <Alert variant='destructive'>
              <AlertCircle className='h-4 w-4' />
              <AlertDescription>{fileError}</AlertDescription>
            </Alert>
          )}

          {/* Selected file card */}
          {showFileCard && (
            <div className='rounded-lg border bg-muted/50 p-4'>
              <div className='flex items-start justify-between'>
                <div className='flex items-start gap-3'>
                  <FileSpreadsheet className='h-5 w-5 text-muted-foreground mt-0.5 shrink-0' />
                  <div className='flex-1 min-w-0'>
                    <p className='text-sm font-medium truncate'>{file.name}</p>
                    <p className='text-xs text-muted-foreground'>
                      {(file.size / 1024).toFixed(1)} KB
                    </p>
                  </div>
                </div>
                {!uploading && (
                  <Button
                    variant='ghost'
                    size='icon'
                    onClick={handleRemoveFile}
                    className='h-7 w-7 shrink-0 -mt-0.5 -mr-1'
                  >
                    <X className='h-4 w-4' />
                  </Button>
                )}
              </div>

              {uploading && (
                <div className='mt-4 space-y-2'>
                  <Progress value={progress} className='h-1.5' />
                  <p className='text-xs text-center text-muted-foreground'>
                    Importing orders… {progress}%
                  </p>
                </div>
              )}
            </div>
          )}

          {/* Result */}
          {result && (
            <div className='space-y-3'>
              <Alert variant={result.success ? 'default' : 'destructive'}>
                {result.success ? (
                  <CheckCircle2 className='h-4 w-4' />
                ) : (
                  <AlertCircle className='h-4 w-4' />
                )}
                <AlertDescription>
                  <p className='font-medium'>{result.message}</p>
                  {result.success && result.imported !== undefined && (
                    <div className='mt-3 text-sm space-y-2'>
                      {(result.imported ?? 0) > 0 && (
                        <div className='flex justify-between'>
                          <span className='text-muted-foreground'>✓ Imported:</span>
                          <span className='font-medium text-green-600 dark:text-green-400'>
                            {result.imported}
                          </span>
                        </div>
                      )}
                      {(result.duplicates ?? 0) > 0 && (
                        <div className='flex justify-between'>
                          <span className='text-muted-foreground'>⊘ Duplicates skipped:</span>
                          <span className='font-medium text-blue-600 dark:text-blue-400'>
                            {result.duplicates}
                          </span>
                        </div>
                      )}
                      {(result.invalid ?? 0) > 0 && (
                        <div className='flex justify-between'>
                          <span className='text-muted-foreground'>⚠ Invalid rows:</span>
                          <span className='font-medium text-orange-600 dark:text-orange-400'>
                            {result.invalid}
                          </span>
                        </div>
                      )}
                      <div className='flex justify-between border-t pt-2'>
                        <span className='text-muted-foreground'>Total rows processed:</span>
                        <span className='font-medium'>{result.total}</span>
                      </div>
                    </div>
                  )}
                </AlertDescription>
              </Alert>

              {result.has_errors && result.errors && result.errors.length > 0 && (
                <div className='rounded-lg border bg-muted/50 p-3 max-h-48 overflow-y-auto'>
                  <p className='text-sm font-medium mb-2'>
                    Issues found ({result.errors.length}):
                  </p>
                  <div className='space-y-2'>
                    {result.errors.slice(0, 10).map((error, index) => (
                      <div key={index} className='text-xs space-y-0.5'>
                        <div className='flex items-center gap-2'>
                          <span className='font-medium'>Row {error.row}:</span>
                          {error.order_number && (
                            <span className='text-muted-foreground'>
                              ({error.order_number})
                            </span>
                          )}
                        </div>
                        <p className='text-muted-foreground'>
                          {error.reason} — {error.details}
                        </p>
                      </div>
                    ))}
                    {result.errors.length > 10 && (
                      <p className='text-xs text-muted-foreground italic'>
                        … and {result.errors.length - 10} more issues
                      </p>
                    )}
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Actions */}
          <div className='flex justify-end gap-2'>
            {result ? (
              <>
                <Button variant='outline' onClick={handleReset}>
                  Import Another
                </Button>
                <Button onClick={handleClose}>Close</Button>
              </>
            ) : (
              <>
                <Button variant='outline' onClick={handleClose} disabled={uploading}>
                  Cancel
                </Button>
                {file && (
                  <Button onClick={handleImport} disabled={uploading}>
                    {uploading ? 'Importing…' : 'Import Orders'}
                  </Button>
                )}
              </>
            )}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}
