import { useState, useRef } from 'react'
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

interface OrdersImportDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  onSuccess?: () => void
}

export function OrdersImportDialog({
  open,
  onOpenChange,
  onSuccess,
}: OrdersImportDialogProps) {
  const [file, setFile] = useState<File | null>(null)
  const [uploading, setUploading] = useState(false)
  const [progress, setProgress] = useState(0)
  const [isDragging, setIsDragging] = useState(false)
  const [result, setResult] = useState<{
    success: boolean
    message: string
    imported?: number
    duplicates?: number
    invalid?: number
    total?: number
    errors?: Array<{
      row: number
      order_number?: string
      reason: string
      details: string
    }>
    has_errors?: boolean
  } | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)

  const validateFile = (file: File): boolean => {
    // Check file type
    if (!file.name.endsWith('.csv') && file.type !== 'text/csv') {
      alert('Please select a CSV file')
      return false
    }

    // Check file size (10MB max)
    if (file.size > 10 * 1024 * 1024) {
      alert('File size must be less than 10MB')
      return false
    }

    return true
  }

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = e.target.files?.[0]
    if (selectedFile && validateFile(selectedFile)) {
      setFile(selectedFile)
      setResult(null)
    }
  }

  const handleDragOver = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault()
    e.stopPropagation()
    setIsDragging(true)
  }

  const handleDragLeave = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault()
    e.stopPropagation()
    setIsDragging(false)
  }

  const handleDrop = (e: React.DragEvent<HTMLDivElement>) => {
    e.preventDefault()
    e.stopPropagation()
    setIsDragging(false)

    const droppedFile = e.dataTransfer.files[0]
    if (droppedFile && validateFile(droppedFile)) {
      setFile(droppedFile)
      setResult(null)
    }
  }

  const handleRemoveFile = () => {
    setFile(null)
    setResult(null)
    if (fileInputRef.current) {
      fileInputRef.current.value = ''
    }
  }

  const handleImport = async () => {
    if (!file) return

    setUploading(true)
    setProgress(0)
    setResult(null)

    const formData = new FormData()
    formData.append('file', file)

    // Simulate progress
    const progressInterval = setInterval(() => {
      setProgress((prev) => Math.min(prev + 10, 90))
    }, 200)

    try {
      const response = await fetch('/api/orders/import', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN':
            document
              .querySelector('meta[name="csrf-token"]')
              ?.getAttribute('content') || '',
        },
        body: formData,
      })

      clearInterval(progressInterval)
      setProgress(100)

      const data = await response.json()

      if (response.ok && data.success) {
        setResult(data)
        // Trigger refresh in the background
        onSuccess?.()
      } else {
        setResult({
          success: false,
          message: data.message || 'Import failed',
        })
      }
    } catch (error) {
      clearInterval(progressInterval)
      setResult({
        success: false,
        message: error instanceof Error ? error.message : 'Import failed',
      })
    } finally {
      setUploading(false)
    }
  }

  const handleClose = () => {
    if (!uploading) {
      setFile(null)
      setResult(null)
      setProgress(0)
      setIsDragging(false)
      if (fileInputRef.current) {
        fileInputRef.current.value = ''
      }
      onOpenChange(false)
    }
  }

  const handleReset = () => {
    setFile(null)
    setResult(null)
    setProgress(0)
    setIsDragging(false)
    if (fileInputRef.current) {
      fileInputRef.current.value = ''
    }
  }

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
          {!file && !result && (
            <div
              onDragOver={handleDragOver}
              onDragLeave={handleDragLeave}
              onDrop={handleDrop}
              className={`flex flex-col items-center justify-center rounded-lg border-2 border-dashed p-12 text-center transition-colors cursor-pointer ${
                isDragging
                  ? 'border-primary bg-primary/5'
                  : 'border-muted-foreground/25 hover:border-muted-foreground/50'
              }`}
              onClick={() => fileInputRef.current?.click()}
            >
              <FileSpreadsheet className={`mb-4 h-12 w-12 ${isDragging ? 'text-primary' : 'text-muted-foreground'}`} />
              <div className='mb-4'>
                <p className='text-sm font-medium'>
                  {isDragging ? 'Drop your CSV file here' : 'Drag & drop or click to select'}
                </p>
                <p className='text-xs text-muted-foreground mt-1'>
                  CSV files only • Maximum 10MB
                </p>
              </div>
              <Button
                variant='outline'
                size='sm'
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
                accept='.csv'
                onChange={handleFileChange}
                className='hidden'
              />
            </div>
          )}

          {file && !result && (
            <div className='rounded-lg border bg-muted/50 p-4'>
              <div className='flex items-start justify-between'>
                <div className='flex items-start gap-3'>
                  <FileSpreadsheet className='h-5 w-5 text-muted-foreground mt-0.5' />
                  <div className='flex-1 min-w-0'>
                    <p className='text-sm font-medium truncate'>{file.name}</p>
                    <p className='text-xs text-muted-foreground'>
                      {(file.size / 1024).toFixed(2)} KB
                    </p>
                  </div>
                </div>
                {!uploading && (
                  <Button
                    variant='ghost'
                    size='sm'
                    onClick={handleRemoveFile}
                    className='h-8 w-8 p-0'
                  >
                    <X className='h-4 w-4' />
                  </Button>
                )}
              </div>

              {uploading && (
                <div className='mt-4 space-y-2'>
                  <Progress value={progress} />
                  <p className='text-xs text-center text-muted-foreground'>
                    Importing orders... {progress}%
                  </p>
                </div>
              )}
            </div>
          )}

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
                      {result.imported > 0 && (
                        <div className='flex justify-between'>
                          <span className='text-muted-foreground'>✓ Imported:</span>
                          <span className='font-medium text-green-600 dark:text-green-400'>
                            {result.imported}
                          </span>
                        </div>
                      )}
                      {result.duplicates !== undefined && result.duplicates > 0 && (
                        <div className='flex justify-between'>
                          <span className='text-muted-foreground'>⊘ Duplicates skipped:</span>
                          <span className='font-medium text-blue-600 dark:text-blue-400'>
                            {result.duplicates}
                          </span>
                        </div>
                      )}
                      {result.invalid !== undefined && result.invalid > 0 && (
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

              {/* Show error details if any */}
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
                          {error.reason} - {error.details}
                        </p>
                      </div>
                    ))}
                    {result.errors.length > 10 && (
                      <p className='text-xs text-muted-foreground italic'>
                        ... and {result.errors.length - 10} more issues
                      </p>
                    )}
                  </div>
                </div>
              )}
            </div>
          )}

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
                <Button
                  variant='outline'
                  onClick={handleClose}
                  disabled={uploading}
                >
                  Cancel
                </Button>
                {file && (
                  <Button
                    onClick={handleImport}
                    disabled={uploading}
                  >
                    {uploading ? 'Importing...' : 'Import Orders'}
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
