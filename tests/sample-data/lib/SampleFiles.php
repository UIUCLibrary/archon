<?php
/**
 * Small synthetic files for digital content: JPEG images drawn with GD (with the thumbnails Archon
 * would create on upload) and one-page PDF documents.
 */
class SampleFiles
{
   /**
    * array(contents, previewLong, previewShort) for a JPEG, the way File::dbStore() stores an upload:
    * previews are JPEG thumbnails 400 and 100 pixels wide.
    */
   public static function jpeg($Label, $Caption)
   {
      $Width = 640 + 40 * mt_rand(0, 8);
      $Height = 480 + 40 * mt_rand(-2, 6);
      $Image = imagecreatetruecolor($Width, $Height);
      $Base = array(mt_rand(90, 200), mt_rand(80, 180), mt_rand(60, 160));
      for($y = 0; $y < $Height; $y += 4)
      {
         $Shade = (int)(40 * $y / $Height);
         $Color = imagecolorallocate($Image, max(0, $Base[0] - $Shade), max(0, $Base[1] - $Shade), max(0, $Base[2] - $Shade));
         imagefilledrectangle($Image, 0, $y, $Width - 1, $y + 3, $Color);
      }
      for($i = mt_rand(4, 9); $i > 0; $i--)
      {
         $Color = imagecolorallocatealpha($Image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255), mt_rand(40, 90));
         $x = mt_rand(0, $Width - 60);
         $y = mt_rand(0, $Height - 60);
         if(mt_rand(0, 1))
         {
            imagefilledellipse($Image, $x, $y, mt_rand(40, 260), mt_rand(40, 260), $Color);
         }
         else
         {
            imagefilledrectangle($Image, $x, $y, $x + mt_rand(30, 240), $y + mt_rand(30, 200), $Color);
         }
      }
      $White = imagecolorallocate($Image, 255, 255, 255);
      $Black = imagecolorallocate($Image, 0, 0, 0);
      imagefilledrectangle($Image, 0, $Height - 46, $Width - 1, $Height - 1, $Black);
      imagestring($Image, 5, 12, $Height - 40, self::ascii($Label), $White);
      imagestring($Image, 3, 12, $Height - 20, self::ascii(substr($Caption, 0, 90)), $White);
      imagestring($Image, 2, 12, 10, 'SAMPLE IMAGE - synthetic test data', $White);

      $Contents = self::encode($Image);
      $Long = self::encode(self::scale($Image, 400));
      $Short = self::encode(self::scale($Image, 100));
      imagedestroy($Image);
      return array($Contents, $Long, $Short);
   }

   private static function scale($Image, $Width)
   {
      $Height = (int)round(imagesy($Image) * $Width / imagesx($Image));
      $Scaled = imagecreatetruecolor($Width, $Height);
      imagecopyresampled($Scaled, $Image, 0, 0, 0, 0, $Width, $Height, imagesx($Image), imagesy($Image));
      return $Scaled;
   }

   private static function encode($Image)
   {
      ob_start();
      imagejpeg($Image, NULL, 82);
      return ob_get_clean();
   }

   private static function ascii($Text)
   {
      $Text = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT', $Text) : $Text;
      return preg_replace('/[^\x20-\x7e]/', '?', (string)$Text);
   }

   /**
    * A valid one-page PDF with a title and a few lines of text.
    */
   public static function pdf($Title, array $Lines)
   {
      $Escape = function($Text) {
         return strtr(self::ascii($Text), array('\\' => '\\\\', '(' => '\\(', ')' => '\\)'));
      };
      $Stream = "BT /F1 18 Tf 72 720 Td (" . $Escape(substr($Title, 0, 60)) . ") Tj ET\n";
      $Stream .= "BT /F1 10 Tf 72 700 Td (SAMPLE DOCUMENT - synthetic test data) Tj ET\n";
      $y = 670;
      foreach($Lines as $Line)
      {
         foreach(str_split($Line, 95) as $Part)
         {
            $Stream .= "BT /F1 11 Tf 72 $y Td (" . $Escape($Part) . ") Tj ET\n";
            $y -= 16;
            if($y < 72)
            {
               break 2;
            }
         }
      }
      $Objects = array(
         '<< /Type /Catalog /Pages 2 0 R >>',
         '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
         '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
         '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
         '<< /Length ' . strlen($Stream) . " >>\nstream\n" . $Stream . "endstream",
      );
      $Pdf = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
      $Offsets = array();
      foreach($Objects as $Index => $Object)
      {
         $Offsets[] = strlen($Pdf);
         $Pdf .= ($Index + 1) . " 0 obj\n" . $Object . "\nendobj\n";
      }
      $Xref = strlen($Pdf);
      $Pdf .= "xref\n0 " . (count($Objects) + 1) . "\n0000000000 65535 f \n";
      foreach($Offsets as $Offset)
      {
         $Pdf .= sprintf("%010d 00000 n \n", $Offset);
      }
      $Pdf .= "trailer\n<< /Size " . (count($Objects) + 1) . " /Root 1 0 R >>\nstartxref\n$Xref\n%%EOF\n";
      return $Pdf;
   }
}
