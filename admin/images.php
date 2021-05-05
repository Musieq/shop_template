<?php
$errors = [];



/** Delete image **/
if (isset($_GET['deleteImageID'])) {
    $deleteImageID = $_GET['deleteImageID'];
    deleteImage($db, $deleteImageID);
}


/** Bulk delete **/
if (isset($_POST['imageBulkOption'])) {
    if ($_POST['imageBulkOption'] == 1) {
        if(!empty($_POST['imageDeleteCheckbox'])) {
            foreach ($_POST['imageDeleteCheckbox'] as $imageBulkDeleteID) {
                deleteImage($db, $imageBulkDeleteID);
            }
        }
    }
}


?>

<div class="row">

    <?php
    displayErrors($errors);
    ?>


    <div class="col-12">
        <h2>Uploaded images</h2>
        <div class="alert-warning callout callout-warning"><strong>Do not remove</strong> uploaded images directly from the directory.</div>


        <!-- Search images by date and title -->
        <form class="row form-width-700 g-3 mb-3" method="get">
            <input type="hidden" name="source" value="images">

            <div class="col-md-6">
                <label for="imageFilterTitle" class="form-label">Search for an image by title</label>
                <input type="text" class="form-control" id="imageFilterTitle" name="imageFilterTitle" value="<?=$_GET['imageFilterTitle'] ?? ''?>">
            </div>

            <div class="col-md-6">
                <label for="imageFilterDate" class="form-label">Select the date of upload</label>
                <select class="form-select" id="imageFilterDate" name="imageFilterDate">
                    <option value="0">Every image</option>
                    <?php
                    /** Create options for select field with dates - image filters **/
                    // Get dates of images uploads
                    $dateQuery = mysqli_query($db, "SELECT upload_date FROM images");
                    $dateArray = [];
                    // Create array with month and year
                    while ($dateQueryResult = mysqli_fetch_assoc($dateQuery)) {
                        $date = strtotime($dateQueryResult['upload_date']);
                        $yearAndMonth = date("F Y", $date);
                        if (!in_array($yearAndMonth, $dateArray)) {
                            array_push($dateArray, $yearAndMonth);
                        }
                    }
                    $dateFilterSelected = $_GET['imageFilterDate'] ?? 0;
                    // Create options to filter by month/year
                    foreach ($dateArray as $value) {
                        $date = strtotime($value);
                        $dateNumeric = date("Ym", $date);
                        if ($dateFilterSelected == $dateNumeric) {
                            echo "<option value='$dateNumeric' selected>$value</option>";
                        } else {
                            echo "<option value='$dateNumeric'>$value</option>";
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-primary" name="imageFilterSubmit" value="filter">Filter</button>
            </div>
        </form>



        <!-- Bulk delete form -->
        <form class="row row-cols-lg-auto g-3" id="imageBulkDeleteForm" name="imageBulkDeleteForm" method="post" action="index.php?<?=http_build_query(array_merge($_GET))?>">

            <div class="col-12">
                <label for="imageBulkOption" class="form-label visually-hidden">Bulk action</label>
                <select class="form-select" id="imageBulkOption" name="imageBulkOption">
                    <option value="0" selected>Bulk action</option>
                    <option value="1">Delete all</option>
                </select>
            </div>

            <div class="col-12">
                <button type="button" class="btn btn-primary" name="imageBulkDelete" onclick="bulkDeleteModal('imageBulkDeleteForm', 'imageBulkOption')">Submit</button>
            </div>



            <div class="table-responsive" style="width: 100%">
                    <table class="table table-images">
                        <thead>
                        <tr>
                            <th scope="col"><input type="checkbox" class="form-check-input" aria-label="Check to delete every image shown below" id="imageDeleteSelectAll" onclick="selectCheckboxes(this.id, 'imageDeleteCheckbox[]')"></th>
                            <th scope="col">Image</th>
                            <th scope="col">Title</th>
                            <th scope="col">Alternative text</th>
                            <th scope="col">Date</th>
                            <th scope="col">Edit</th>
                            <th scope="col">Delete</th>
                        </tr>
                        </thead>
                        <tbody>

                        <?php
                        // Variables for pagination
                        $imagesCountQuery = mysqli_query($db, "SELECT COUNT(id) FROM images");
                        $imagesCount = mysqli_fetch_array($imagesCountQuery)[0];
                        $limit = 20;
                        $pages = ceil($imagesCount / $limit);
                        $currentPage = $_GET['page'] ?? 1;
                        $offset = ($currentPage - 1) * $limit;


                        /** Filter images by title and/or by chosen date **/
                        $titleFilter = '';
                        $dateFilter = 0;
                        if (isset($_GET['imageFilterSubmit'])) {
                            $titleFilter = $_GET['imageFilterTitle'];
                            $dateFilter = $_GET['imageFilterDate'];
                            $yearFilter = substr($dateFilter, 0, 4);
                            $monthFilter = substr($dateFilter, 4, 2);
                        }

                        if ($titleFilter != '' && $dateFilter != 0) {
                            $titleFilter = '%'.$titleFilter.'%';
                            $displayImagesQuery = mysqli_prepare($db, "SELECT * FROM images WHERE title LIKE ? AND YEAR(upload_date) LIKE ? AND MONTH(upload_date) LIKE ? ORDER BY upload_date DESC LIMIT $limit OFFSET $offset");
                            mysqli_stmt_bind_param($displayImagesQuery, "sii",$titleFilter, $yearFilter, $monthFilter);
                        } elseif ($titleFilter != '') {
                            $titleFilter = '%'.$titleFilter.'%';
                            $displayImagesQuery = mysqli_prepare($db, "SELECT * FROM images WHERE title LIKE ? ORDER BY upload_date DESC LIMIT $limit OFFSET $offset");
                            mysqli_stmt_bind_param($displayImagesQuery, "s",$titleFilter);
                        } elseif ($dateFilter != 0) {
                            $displayImagesQuery = mysqli_prepare($db, "SELECT * FROM images WHERE YEAR(upload_date) LIKE ? AND MONTH(upload_date) LIKE ? ORDER BY upload_date DESC LIMIT $limit OFFSET $offset");
                            mysqli_stmt_bind_param($displayImagesQuery, "ii",$yearFilter, $monthFilter);
                        } else {
                            $displayImagesQuery = mysqli_prepare($db, "SELECT * FROM images ORDER BY upload_date DESC LIMIT $limit OFFSET $offset");
                        }

                        // Execute prepared statement
                        mysqli_stmt_execute($displayImagesQuery);
                        $displayImagesGetResults = mysqli_stmt_get_result($displayImagesQuery);

                        /** Display images **/
                        while($displayImagesResults = mysqli_fetch_assoc($displayImagesGetResults)){
                            $imageID = $displayImagesResults['id'];
                            $imageUniqueName = $displayImagesResults['unique_name'];
                            $imageTitle = $displayImagesResults['title'];
                            $imageAlt = $displayImagesResults['alt'];
                            $uploadDate = $displayImagesResults['upload_date'];
                            $fullPath = $displayImagesResults['path'];

                            // Get path without extension and extension
                            $path = pathinfo($fullPath, PATHINFO_DIRNAME);
                            $path .= '/'.pathinfo($fullPath, PATHINFO_FILENAME);
                            $extension = pathinfo($fullPath, PATHINFO_EXTENSION);

                            // Create URL when deleting images after applying filters
                            $deleteURL = http_build_query(array_merge($_GET,['deleteImageID' => $imageID]));
                            ?>

                            <tr>
                                <td><input type="checkbox" name="imageDeleteCheckbox[]" class="form-check-input" value="<?=$imageID?>" aria-label="Check for bulk deleting image <?=$imageTitle?>"></td>
                                <td><a href="../<?=$fullPath?>">
                                        <div class="admin-image-container" style="background-image: url('../<?=$path?>-thumbnail.<?=$extension?>') "></div>
                                    </a>
                                </td>
                                <td><?=$imageTitle?></td>
                                <td><?=$imageAlt?></td>
                                <td><?=$uploadDate?></td>
                                <td><a href="index.php?source=images&editImageID=<?=$imageID?>">Edit</td>
                                <td><a href="index.php?<?=$deleteURL?>" class="link-danger delete-image-link" data-bs-toggle="modal" data-bs-target="#modalImageDeleteWarning">Delete</td>
                            </tr>

                            <?php
                        }
                        mysqli_stmt_close($displayImagesQuery)

                        ?>

                        </tbody>
                    </table>
                </div>



        </form>

        <?php
            // Create pagination if there is more than 1 page
            if ($pages > 1) :
            ?>

                <nav aria-label="Pagination for images">
                    <ul class="pagination justify-content-center">

                        <?php
                        // Unset $_GET['page'] so we can set it when building link. Necessary for pagination when filters are applied.
                        unset($_GET['page']);
                        if ($pages <= 3) :
                            for ($i = 1; $i <= $pages; $i++) :
                                $paginationURL = http_build_query(array_merge($_GET,['page' => $i]));
                            ?>

                                <li class="page-item <?php if($i == $currentPage) { echo 'active'; } ?>" <?php if($i == $currentPage) { echo 'aria-current="page"'; } ?>>
                                    <a class="page-link" href="index.php?<?=$paginationURL?>"><?=$i?></a>
                                </li>

                            <?php
                            endfor;
                        else :
                        ?>

                            <?php
                            if ($currentPage == 1) :
                            ?>
                                <li class="page-item disabled">
                                    <span class="page-link">First</span>
                                </li>
                            <?php
                            else :
                                $paginationURL = http_build_query(array_merge($_GET,['page' => 1]));
                            ?>
                                <li class="page-item">
                                    <a class="page-link" href="index.php?<?=$paginationURL?>">First</a>
                                </li>
                            <?php
                            endif;
                            ?>

                            <?php
                            if ($currentPage > 1) :
                                $paginationURL = http_build_query(array_merge($_GET,['page' => $currentPage-1]));
                            ?>
                                <li class="page-item">
                                    <a class="page-link" href="index.php?<?=$paginationURL?>"><?=$currentPage-1?></a>
                                </li>
                            <?php
                            endif;
                            ?>

                                <li class="page-item active" aria-current="page">
                                    <span class="page-link"><?=$currentPage?></span>
                                </li>

                            <?php
                            if ($currentPage < $pages) :
                                $paginationURL = http_build_query(array_merge($_GET,['page' => $currentPage+1]));
                            ?>
                                <li class="page-item">
                                    <a class="page-link" href="index.php?<?=$paginationURL?>"><?=$currentPage+1?></a>
                                </li>
                            <?php
                            endif;
                            ?>

                            <?php
                            if ($currentPage == $pages) :
                                ?>
                                <li class="page-item disabled">
                                    <span class="page-link">Last</span>
                                </li>
                            <?php
                            else :
                                $paginationURL = http_build_query(array_merge($_GET,['page' => $pages]));
                                ?>
                                <li class="page-item">
                                    <a class="page-link" href="index.php?<?=$paginationURL?>">Last</a>
                                </li>
                            <?php
                            endif;
                            ?>

                        <?php
                        endif;
                        ?>

                    </ul>
                </nav>

            <?php
            endif;
        ?>




    </div>

</div>



<!-- Modal - delete image -->
<div class="modal fade" id="modalImageDeleteWarning" tabindex="-1" aria-labelledby="modalImageDeleteWarningLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalImageDeleteWarningLabel">Delete category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this image? This operation cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="deleteImageConfirm">Delete image</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.onload = function() { deleteAndShowModal('delete-image-link', 'deleteImageConfirm') };
</script>