<?php

/**
 * Gets an array [[sin(x), cos(x)], ...] of the Angles
 *
 * @param array $Angles
 * @return array
 */
function get_TAngles(array $Angles)
{
    return [
        [sin($Angles[0]), cos($Angles[0])],
        [sin($Angles[1]), cos($Angles[1])],
        [sin($Angles[2]), cos($Angles[2])],
    ];
}

/**
 * Rotates coords via Euler Angles
 *
 * @param array $point point [x, y, z]
 * @param array $Angles
 * @param array $TAngles
 * @return array new point
 */
function rotate(array $point, array $Angles, array $TAngles)
{
    if ($Angles[0] != 0)
    {
        list($point[0], $point[1], $point[2]) = [
            $point[0],
            $point[1]*$TAngles[0][1] - $point[2]*$TAngles[0][0],
            $point[1]*$TAngles[0][0] + $point[2]*$TAngles[0][1],
        ];
    }

    if ($Angles[1] != 0)
    {
        list($point[0], $point[1], $point[2]) = [
            $point[0]*$TAngles[1][1] + $point[2]*$TAngles[1][0],
            $point[1],
            -$point[0]*$TAngles[1][0] + $point[2]*$TAngles[1][1],
        ];
    }


    if ($Angles[2] != 0)
    {
        list($point[0], $point[1], $point[2]) = [
            $point[0]*$TAngles[2][1] - $point[1]*$TAngles[2][0],
            $point[0]*$TAngles[2][0] + $point[1]*$TAngles[2][1],
            $point[2],
        ];
    }

    return $point;
}

/**
 * Gets a map of source points
 *
 * @param type $Width
 * @param type $Height
 * @param type $Depth
 * @return array an array of points
 */
function get_points($Width, $Height, $Depth)
{
    $points = [];

    for ($z = 1; $z < $Depth; $z++)
    {
        for ($y = 0; $y < $Height; $y++)
        {
            $points[] = [0, $y, $z];
            $points[] = [$Width-1, $y, $z];
        }

        for ($x = 0; $x < $Width; $x++)
        {
            $points[] = [$x, 0, $z];
            $points[] = [$x, $Height-1, $z];
        }
    }

    for ($y = 0; $y < $Height; $y++)
    {
        for ($x = 0; $x < $Width; $x++)
        {
            $points[] = [$x, $y, 0];
        }
    }

    return $points;
}

/**
 * Gets points of box (total 8 points)
 * 
 * @param int $Width source width
 * @param int $Height source height
 * @param int $Depth source depth
 * @param array $Angles
 * @param array $TAngles
 * @return array an array of points
 */
function get_box($Width, $Height, $Depth, $Angles, $TAngles)
{
    $box = [];
    
    foreach ([0, $Depth-1] as $z)
    {
        foreach ([0, $Height-1] as $y)
        {
            foreach ([0, $Width-1] as $x)
            {
                $box[] = rotate([$x, $y, $z], $Angles, $TAngles);
            }
        }
    }

    return $box;
}

/**
 * Gets points limits of the box (total 8 points)
 * @param array $box
 * @return array
 */
function get_limits($box)
{
    $limits = [
        ['min' => PHP_INT_MAX, 'max' => ~PHP_INT_MAX], // x
        ['min' => PHP_INT_MAX, 'max' => ~PHP_INT_MAX], // y
        ['min' => PHP_INT_MAX, 'max' => ~PHP_INT_MAX], // z
    ];

    foreach ($box as $point)
    {
        for ($i = 0; $i < 3; $i++)
        {
            if ($point[$i] < $limits[$i]['min']) { $limits[$i]['min'] = $point[$i]; }
            elseif ($point[$i] > $limits[$i]['max']) { $limits[$i]['max'] = $point[$i]; }
        }
    }

    return $limits;
}

/**
 * Gets a zoom (scaling)
 * 
 * @param float $z z-axis [px]
 * @return float scaling from 0 to 1
 */
function get_zoom($z)
{
    return 3000/(3000 + $z);
}

/**
 * Gets image allocated color by its index
 * 
 * @staticvar array $allocated
 * @param resource $dest
 * @param int $color
 * @return int
 */
function get_allocated_color($dest, $color)
{
    static $allocated = [];

    if (isset($allocated[$color]))
    {
        return $allocated[$color];
    }
    
    $allocated[$color] = imagecolorallocate($dest, ($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF);
    
    return $allocated[$color];
}

/**
 * Get smoothed border colors
 * 
 * @param resource $src
 * @param int $Width
 * @param int $Height
 * @param resource $dest
 * @return array
 */
function get_border_colors($src, $Width, $Height)
{
    $colors = [];

    for ($y = 0; $y < $Height; $y++)
    {
        foreach ([0, $Width-1] as $x)
        {
            $color = imagecolorat($src, $x, $y);
            $colors[$y][$x] = [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
        }
    }

    for ($x = 0; $x < $Width; $x++)
    {
        foreach ([0, $Height-1] as $y)
        {
            $color = imagecolorat($src, $x, $y);
            $colors[$y][$x] = [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
        }
    }

    $blured = [];
    $light = 0x22;

    for ($y = 0; $y < $Height; $y++)
    {
        foreach ([0, $Width-1] as $x)
        {
            $color = $colors[$y][$x];
            $d = 1;
            for ($j = -5; $j <= 5; $j++)
            {
                if (isset($colors[$y+$j][$x]))
                {
                    for ($i = 0; $i < 3; $i++) { $color[$i] += $colors[$y+$j][$x][$i]; }
                    $d++;
                }
            }
            
            $blured[$y][$x] = [$color[0]/$d+$light, $color[1]/$d+$light, $color[2]/$d+$light];
        }
    }

    for ($x = 0; $x < $Width; $x++)
    {
        foreach ([0, $Height-1] as $y)
        {
            $color = $colors[$y][$x];
            $d = 1;
            for ($j = -5; $j <= 5; $j++)
            {
                if (isset($colors[$y][$x+$j]))
                {
                    for ($i = 0; $i < 3; $i++) { $color[$i] += $colors[$y][$x+$j][$i]; }
                    $d++;
                }
            }

            $blured[$y][$x] = [$color[0]/$d+$light, $color[1]/$d+$light, $color[2]/$d+$light];
        }
    }

    return $blured;
}

/**
 * Transforms 2D image to 3D and stores its projection in 2D
 * @param string $input Path to source file
 * @param string $output Path to destination file
 * @param array $Angles Euler angles (α, β, γ) [in radians]
 * @param type $Depth Depth of the source image (z-axis) [in pixels]
 * @param type $Padding Minimum padding between borders and 3D image projection
 */
function transform2dto3d($input, $output, array $Angles = [-M_PI/9, -M_PI/6, 0], $Depth = 40, $Padding = 50)
{
    $TAngles = get_TAngles($Angles);

    // load src image

    $extension = strtolower(strrchr($input, '.'));
    switch ($extension)
    {
        case '.jpg':
        case '.jpeg':
            $src = @imagecreatefromjpeg($input);
            break;
        case '.gif':
            $src = @imagecreatefromgif($input);
            break;
        case '.png':
            $src = @imagecreatefrompng($input);
            break;
        default:
            trigger_error('unknown image format');
            return false;
    }

    $src_Width = imagesx($src);
    $src_Height = imagesy($src);

    // find geometry limits

    $center = rotate([$src_Width/2, $src_Height/2, $Depth/2], $Angles, $TAngles);
    $box = get_box($src_Width, $src_Height, $Depth, $Angles, $TAngles);
    $limits = get_limits($box);

    // apply zoom

    foreach ($box as &$point)
    {
        $zoom = get_zoom($point[2] - $limits[2]['min']);
        $point[0] = ($point[0] - $center[0])*$zoom;
        $point[1] = ($point[1] - $center[1])*$zoom;
    }

    // find new proection limits

    $new_limits = get_limits($box);

    // create new image

    $dest_Width = $Padding*2 + $new_limits[0]['max'] - $new_limits[0]['min'];
    $dest_Height = $Padding*2 + $new_limits[1]['max'] - $new_limits[1]['min'];
    $real_center = [$Padding - $new_limits[0]['min'], $Padding - $new_limits[1]['min']];
    $dest = imagecreatetruecolor($dest_Width, $dest_Height);
    imagefill($dest, 0, 0, get_allocated_color($dest, 0xFFFFFF));

    // generate border colors
    $border_colors = get_border_colors($src, $src_Width, $src_Height);

    // draw points

    $points = get_points($src_Width, $src_Height, $Depth);
    foreach ($points as $point)
    {
        if ($point[2] > 0)
        {
            $color = $border_colors[$point[1]][$point[0]];
            $color = imagecolorallocate($dest, $color[0], $color[1], $color[2]);
        }
        else
        {
            $color = get_allocated_color($dest, imagecolorat($src, $point[0], $point[1]));
        }

        $Point = rotate($point, $Angles, $TAngles);

        $zoom = get_zoom($Point[2] - $limits[2]['min']);

        $x = $real_center[0] + ($Point[0] - $center[0])*$zoom;
        $y = $real_center[1] + ($Point[1] - $center[1])*$zoom;

        imagesetpixel($dest, $x, $y, $color);
    }

    // store the new image

    imagejpeg($dest, $output);

    $extension = strtolower(strrchr($output, '.'));
    switch ($extension)
    {
        case '.jpg':
        case '.jpeg':
            imagejpeg($dest, $output);
            break;
        case '.gif':
            imagegif($dest, $output);
            break;
        case '.png':
            imagepng($dest, $output);
            break;
        default:
            trigger_error('unknown image format');
            return false;
    }

    imagedestroy($dest);
}
