-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 04, 2025 at 07:46 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `asrp`
--

-- --------------------------------------------------------

--
-- Table structure for table `client`
--

CREATE TABLE `client` (
  `Client_ID` int(11) NOT NULL,
  `Client_fn` varchar(255) DEFAULT NULL COMMENT 'First name',
  `Client_ln` varchar(255) DEFAULT NULL COMMENT 'Last name',
  `Client_Email` varchar(255) NOT NULL COMMENT 'Email address',
  `Client_Phone` varchar(20) DEFAULT NULL COMMENT 'Phone number',
  `C_username` varchar(50) NOT NULL COMMENT 'Username (unique)',
  `C_password` varchar(255) NOT NULL COMMENT 'Password (hashed)',
  `Status` enum('Active','Inactive') NOT NULL DEFAULT 'Active' COMMENT 'Account status'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client`
--

INSERT INTO `client` (`Client_ID`, `Client_fn`, `Client_ln`, `Client_Email`, `Client_Phone`, `C_username`, `C_password`, `Status`) VALUES
(33, 'Luke', 'Tolentino', '111@gmail.com', '09090909090', '222', '$2y$10$lnrUfTK.oXGSXbWCBL61heZZ6PwYw/o1l65DCrzZSNVcxXYAfk6ma', 'Active'),
(34, 'Luke', 'Tolentino', 'gabrialnakupa@gmail.com', '09668257301', '4444', '$2y$10$v8U.Anl43.MtnQNxihLp0.56InNaA9aKEJZsw9juLUG3FMFNeMRCe', 'Active'),
(35, 'Mark', 'Tolentino', 'afaafawefwa3bd@gmail.com', '09668257301', '555', '$2y$10$scxqhns5pHlsJncbN3ANJu2O1nYYdgMxZJPT3zoEQ8XEP.3qVdZtK', 'Active'),
(36, 'Luke', 'Masalunga', 'dadwad@gmail.com', '09668257301', '666', '$2y$10$oEQTmASx/1ZouazxjhrZUOvLV3wiUidwb9q.klheUD/OWRTiNtgOq', 'Active'),
(37, 'Kisses', 'Darling', 'Smoochie@gmail.com', '09993912723', 'Kisses', '$2y$10$s5/Lkb.c.5UoLZuFnlR1.eHlw64RyL.LOpoKcjaGJ8rxFoNlQ4yGK', 'Active'),
(38, 'ADASDAWD', 'ASDWADAW', 'dasdwa@gmail.com', '09668257301', '111', '$2y$10$snUgAiFB52v5X54oAp5c/ubB7MnVO7INH7nA9JTFUelH1FBJTdAd6', 'Active'),
(39, 'Luke', 'Tolentino', 'www@gmail.com', '09668257301', '444', '$2y$10$N6CfOcyab6kQqw.wUK2WQ.1RoLfMT4sT9GMcEJf.QiTMLx6frRVi2', 'Active'),
(40, 'Mike', 'Baral', 'dadwadw@gmail.com', '09668257301', 'titi', '$2y$10$xel8Fh/M0Gh7DXrD1adjhOLllF/NTjjwzXQD9WVYZS.j5SshXuuV.', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `clientfeedback`
--

CREATE TABLE `clientfeedback` (
  `Feedback_ID` int(11) NOT NULL,
  `CS_ID` int(11) DEFAULT NULL,
  `Rating` int(11) DEFAULT NULL CHECK (`Rating` between 1 and 5),
  `Comments` text DEFAULT NULL,
  `Dates` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clientspace`
--

CREATE TABLE `clientspace` (
  `CS_ID` int(11) NOT NULL,
  `Space_ID` int(11) DEFAULT NULL,
  `Client_ID` int(11) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `BusinessPhoto` varchar(255) DEFAULT NULL,
  `BusinessPhoto1` varchar(255) DEFAULT NULL,
  `BusinessPhoto2` varchar(255) DEFAULT NULL,
  `BusinessPhoto3` varchar(255) DEFAULT NULL,
  `BusinessPhoto4` varchar(255) DEFAULT NULL,
  `BusinessPhoto5` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clientspace`
--

INSERT INTO `clientspace` (`CS_ID`, `Space_ID`, `Client_ID`, `active`, `BusinessPhoto`, `BusinessPhoto1`, `BusinessPhoto2`, `BusinessPhoto3`, `BusinessPhoto4`, `BusinessPhoto5`) VALUES
(73, 57, 39, 1, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `free_message`
--

CREATE TABLE `free_message` (
  `Message_ID` int(11) NOT NULL,
  `Client_Name` varchar(255) NOT NULL,
  `Client_Email` varchar(255) NOT NULL,
  `Client_Phone` varchar(30) DEFAULT NULL,
  `Message_Text` text NOT NULL,
  `Sent_At` datetime DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `free_message`
--

INSERT INTO `free_message` (`Message_ID`, `Client_Name`, `Client_Email`, `Client_Phone`, `Message_Text`, `Sent_At`, `is_deleted`) VALUES
(1, 'Romeo Paolo', 'romeo@gmail.com', '096682532', 'tanga kaba', '2025-08-11 06:42:45', 1),
(2, 'fawfa', 'romeopaolotolen@gmail.com', 'dwadwa', 'gago kaba', '2025-08-11 06:45:30', 1),
(3, 'Prinz', 'djahdjahw@GMAIL.com', '09090', 'kupal', '2025-08-11 12:35:16', 1),
(4, 'titi', 'romeo@gmail.com', '09090', 'adwadwa', '2025-08-13 13:55:07', 1),
(5, 'sdadwa', 'dwadwa@gmail.com', '09090', 'adwadwa', '2025-08-14 01:01:45', 0),
(6, 'Romeo Paolo', 'romeo@gmail.com', '09090', 'kupal ka', '2025-08-29 10:07:22', 0),
(7, 'Romeo Paolo', 'romeo@gmail.com', '09090', 'axaSDSA', '2025-09-03 22:19:34', 0);

-- --------------------------------------------------------

--
-- Table structure for table `handyman`
--

CREATE TABLE `handyman` (
  `Handyman_ID` int(11) NOT NULL,
  `Handyman_fn` varchar(255) DEFAULT NULL,
  `Handyman_ln` varchar(255) DEFAULT NULL,
  `Phone` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `handyman`
--

INSERT INTO `handyman` (`Handyman_ID`, `Handyman_fn`, `Handyman_ln`, `Phone`) VALUES
(1, 'Michael', 'Jordan', '09998998');

-- --------------------------------------------------------

--
-- Table structure for table `handymanjob`
--

CREATE TABLE `handymanjob` (
  `HJ_ID` int(11) NOT NULL,
  `Handyman_ID` int(11) DEFAULT NULL,
  `JobType_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `handymanjob`
--

INSERT INTO `handymanjob` (`HJ_ID`, `Handyman_ID`, `JobType_ID`) VALUES
(1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `invoice`
--

CREATE TABLE `invoice` (
  `Invoice_ID` int(11) NOT NULL,
  `Client_ID` int(11) DEFAULT NULL,
  `InvoiceDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `InvoiceTotal` decimal(10,2) DEFAULT NULL,
  `Status` varchar(10) DEFAULT 'unpaid',
  `Space_ID` int(11) DEFAULT NULL,
  `Flow_Status` varchar(10) NOT NULL DEFAULT 'new',
  `Chat_ID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice`
--

INSERT INTO `invoice` (`Invoice_ID`, `Client_ID`, `InvoiceDate`, `EndDate`, `InvoiceTotal`, `Status`, `Space_ID`, `Flow_Status`, `Chat_ID`) VALUES
(1161, 37, '2025-08-29', '2025-09-01', 11000.00, 'kicked', 58, 'done', NULL),
(1162, 39, '2025-09-02', '2025-09-07', 11000.00, 'unpaid', 57, 'new', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `invoice_chat`
--

CREATE TABLE `invoice_chat` (
  `Chat_ID` int(11) NOT NULL,
  `Invoice_ID` int(11) NOT NULL,
  `Sender_Type` enum('admin','client','system') NOT NULL,
  `Sender_ID` int(11) DEFAULT NULL,
  `Message` text DEFAULT NULL,
  `Image_Path` varchar(255) DEFAULT NULL,
  `Created_At` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoice_chat`
--

INSERT INTO `invoice_chat` (`Chat_ID`, `Invoice_ID`, `Sender_Type`, `Sender_ID`, `Message`, `Image_Path`, `Created_At`) VALUES
(249, 1162, 'client', 39, 'sup', NULL, '2025-09-04 01:12:49'),
(250, 1162, 'admin', 1, 'nigga what', NULL, '2025-09-04 01:13:00'),
(251, 1162, 'client', 39, 'kupal', NULL, '2025-09-05 01:14:19');

-- --------------------------------------------------------

--
-- Table structure for table `jobtype`
--

CREATE TABLE `jobtype` (
  `JobType_ID` int(11) NOT NULL,
  `JobType_Name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobtype`
--

INSERT INTO `jobtype` (`JobType_ID`, `JobType_Name`) VALUES
(1, 'Plumbing');

-- --------------------------------------------------------

--
-- Table structure for table `maintenancerequest`
--

CREATE TABLE `maintenancerequest` (
  `Request_ID` int(11) NOT NULL,
  `Client_ID` int(11) DEFAULT NULL,
  `Space_ID` int(11) DEFAULT NULL,
  `Handyman_ID` int(11) DEFAULT NULL,
  `RequestDate` date DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenancerequeststatushistory`
--

CREATE TABLE `maintenancerequeststatushistory` (
  `MRSH_ID` int(11) NOT NULL,
  `Request_ID` int(11) DEFAULT NULL,
  `StatusChangeDate` date DEFAULT NULL,
  `NewStatus` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rentalrequest`
--

CREATE TABLE `rentalrequest` (
  `Request_ID` int(11) NOT NULL,
  `Client_ID` int(11) NOT NULL,
  `Space_ID` int(11) NOT NULL,
  `StartDate` date NOT NULL,
  `EndDate` date NOT NULL,
  `Status` enum('Pending','Accepted','Rejected') DEFAULT 'Pending',
  `Flow_Status` varchar(10) NOT NULL DEFAULT 'new',
  `Requested_At` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rentalrequest`
--

INSERT INTO `rentalrequest` (`Request_ID`, `Client_ID`, `Space_ID`, `StartDate`, `EndDate`, `Status`, `Flow_Status`, `Requested_At`) VALUES
(1158, 37, 58, '2025-08-29', '2025-09-01', 'Rejected', 'new', '2025-08-29 10:35:16'),
(1159, 39, 57, '2025-09-02', '2025-09-07', 'Accepted', 'new', '2025-09-02 12:53:12'),
(1160, 39, 58, '2025-09-03', '2025-10-03', 'Pending', 'new', '2025-09-03 15:22:43');

-- --------------------------------------------------------

--
-- Table structure for table `space`
--

CREATE TABLE `space` (
  `Space_ID` int(11) NOT NULL,
  `Name` varchar(255) DEFAULT NULL,
  `SpaceType_ID` int(11) DEFAULT NULL,
  `UA_ID` int(11) DEFAULT NULL,
  `Street` varchar(255) DEFAULT NULL,
  `Brgy` varchar(255) DEFAULT NULL,
  `City` varchar(255) DEFAULT NULL,
  `Photo` varchar(255) DEFAULT NULL,
  `Price` decimal(10,2) DEFAULT NULL,
  `Flow_Status` varchar(10) NOT NULL DEFAULT 'new',
  `Photo1` varchar(255) DEFAULT NULL,
  `Photo2` varchar(255) DEFAULT NULL,
  `Photo3` varchar(255) DEFAULT NULL,
  `Photo4` varchar(255) DEFAULT NULL,
  `Photo5` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `space`
--

INSERT INTO `space` (`Space_ID`, `Name`, `SpaceType_ID`, `UA_ID`, `Street`, `Brgy`, `City`, `Photo`, `Price`, `Flow_Status`, `Photo1`, `Photo2`, `Photo3`, `Photo4`, `Photo5`) VALUES
(57, 'Space 4', 2, 1, 'General Luna Strt', '10', 'Lipa City', 'adminunit_1754886853.jpg', 11000.00, 'old', NULL, NULL, NULL, NULL, NULL),
(58, 'Space 5', 3, 1, 'General Luna Strt', '10', 'Lipa City', 'adminunit_1756431401.jfif', 11000.00, 'new', NULL, NULL, NULL, NULL, NULL),
(59, 'Space 1', 2, 1, 'General Luna Strt', '10', 'Lipa City', 'adminunit_1756921777_7782.jfif', 11000.00, 'new', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `spaceavailability`
--

CREATE TABLE `spaceavailability` (
  `Availability_ID` int(11) NOT NULL,
  `Space_ID` int(11) DEFAULT NULL,
  `StartDate` date DEFAULT NULL,
  `EndDate` date DEFAULT NULL,
  `Status` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `spaceavailability`
--

INSERT INTO `spaceavailability` (`Availability_ID`, `Space_ID`, `StartDate`, `EndDate`, `Status`) VALUES
(182, 57, NULL, NULL, 'Available'),
(184, 58, NULL, NULL, 'Available'),
(185, 58, '2025-08-29', '2025-09-02', 'Available'),
(186, 57, '2025-09-02', '2025-09-07', 'Occupied'),
(187, 59, NULL, NULL, 'Available');

-- --------------------------------------------------------

--
-- Table structure for table `spacetype`
--

CREATE TABLE `spacetype` (
  `SpaceType_ID` int(11) NOT NULL,
  `SpaceTypeName` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `spacetype`
--

INSERT INTO `spacetype` (`SpaceType_ID`, `SpaceTypeName`) VALUES
(1, 'Unit'),
(2, 'Space'),
(3, 'Apartment');

-- --------------------------------------------------------

--
-- Table structure for table `useraccounts`
--

CREATE TABLE `useraccounts` (
  `UA_ID` int(11) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `Type` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `useraccounts`
--

INSERT INTO `useraccounts` (`UA_ID`, `username`, `password`, `Type`) VALUES
(1, 'rom_telents', '$2y$10$ezxUEy057HjkAVPMHoxGt.wyV2yVygiMgjonr5k9Ydkz5vraHobyG', 'Admin');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `client`
--
ALTER TABLE `client`
  ADD PRIMARY KEY (`Client_ID`),
  ADD UNIQUE KEY `C_username_UNIQUE` (`C_username`),
  ADD UNIQUE KEY `Client_Email_UNIQUE` (`Client_Email`);

--
-- Indexes for table `clientfeedback`
--
ALTER TABLE `clientfeedback`
  ADD PRIMARY KEY (`Feedback_ID`),
  ADD KEY `CS_ID` (`CS_ID`);

--
-- Indexes for table `clientspace`
--
ALTER TABLE `clientspace`
  ADD PRIMARY KEY (`CS_ID`),
  ADD KEY `Space_ID` (`Space_ID`),
  ADD KEY `Client_ID` (`Client_ID`);

--
-- Indexes for table `free_message`
--
ALTER TABLE `free_message`
  ADD PRIMARY KEY (`Message_ID`);

--
-- Indexes for table `handyman`
--
ALTER TABLE `handyman`
  ADD PRIMARY KEY (`Handyman_ID`);

--
-- Indexes for table `handymanjob`
--
ALTER TABLE `handymanjob`
  ADD PRIMARY KEY (`HJ_ID`),
  ADD KEY `Handyman_ID` (`Handyman_ID`),
  ADD KEY `JobType_ID` (`JobType_ID`);

--
-- Indexes for table `invoice`
--
ALTER TABLE `invoice`
  ADD PRIMARY KEY (`Invoice_ID`),
  ADD KEY `Client_ID` (`Client_ID`),
  ADD KEY `idx_status_date` (`Status`,`InvoiceDate`),
  ADD KEY `idx_client` (`Client_ID`),
  ADD KEY `idx_space` (`Space_ID`);

--
-- Indexes for table `invoice_chat`
--
ALTER TABLE `invoice_chat`
  ADD PRIMARY KEY (`Chat_ID`),
  ADD KEY `Invoice_ID` (`Invoice_ID`);

--
-- Indexes for table `jobtype`
--
ALTER TABLE `jobtype`
  ADD PRIMARY KEY (`JobType_ID`);

--
-- Indexes for table `maintenancerequest`
--
ALTER TABLE `maintenancerequest`
  ADD PRIMARY KEY (`Request_ID`),
  ADD KEY `Client_ID` (`Client_ID`),
  ADD KEY `Space_ID` (`Space_ID`),
  ADD KEY `Handyman_ID` (`Handyman_ID`);

--
-- Indexes for table `maintenancerequeststatushistory`
--
ALTER TABLE `maintenancerequeststatushistory`
  ADD PRIMARY KEY (`MRSH_ID`),
  ADD KEY `Request_ID` (`Request_ID`);

--
-- Indexes for table `rentalrequest`
--
ALTER TABLE `rentalrequest`
  ADD PRIMARY KEY (`Request_ID`),
  ADD KEY `Client_ID` (`Client_ID`),
  ADD KEY `Space_ID` (`Space_ID`);

--
-- Indexes for table `space`
--
ALTER TABLE `space`
  ADD PRIMARY KEY (`Space_ID`),
  ADD KEY `SpaceType_ID` (`SpaceType_ID`),
  ADD KEY `UA_ID` (`UA_ID`);

--
-- Indexes for table `spaceavailability`
--
ALTER TABLE `spaceavailability`
  ADD PRIMARY KEY (`Availability_ID`),
  ADD KEY `Space_ID` (`Space_ID`);

--
-- Indexes for table `spacetype`
--
ALTER TABLE `spacetype`
  ADD PRIMARY KEY (`SpaceType_ID`);

--
-- Indexes for table `useraccounts`
--
ALTER TABLE `useraccounts`
  ADD PRIMARY KEY (`UA_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `client`
--
ALTER TABLE `client`
  MODIFY `Client_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `clientfeedback`
--
ALTER TABLE `clientfeedback`
  MODIFY `Feedback_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `clientspace`
--
ALTER TABLE `clientspace`
  MODIFY `CS_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT for table `free_message`
--
ALTER TABLE `free_message`
  MODIFY `Message_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `handyman`
--
ALTER TABLE `handyman`
  MODIFY `Handyman_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `handymanjob`
--
ALTER TABLE `handymanjob`
  MODIFY `HJ_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `invoice`
--
ALTER TABLE `invoice`
  MODIFY `Invoice_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1163;

--
-- AUTO_INCREMENT for table `invoice_chat`
--
ALTER TABLE `invoice_chat`
  MODIFY `Chat_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=252;

--
-- AUTO_INCREMENT for table `jobtype`
--
ALTER TABLE `jobtype`
  MODIFY `JobType_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `maintenancerequest`
--
ALTER TABLE `maintenancerequest`
  MODIFY `Request_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `maintenancerequeststatushistory`
--
ALTER TABLE `maintenancerequeststatushistory`
  MODIFY `MRSH_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `rentalrequest`
--
ALTER TABLE `rentalrequest`
  MODIFY `Request_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1161;

--
-- AUTO_INCREMENT for table `space`
--
ALTER TABLE `space`
  MODIFY `Space_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `spaceavailability`
--
ALTER TABLE `spaceavailability`
  MODIFY `Availability_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=189;

--
-- AUTO_INCREMENT for table `spacetype`
--
ALTER TABLE `spacetype`
  MODIFY `SpaceType_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `useraccounts`
--
ALTER TABLE `useraccounts`
  MODIFY `UA_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `clientfeedback`
--
ALTER TABLE `clientfeedback`
  ADD CONSTRAINT `clientfeedback_ibfk_1` FOREIGN KEY (`CS_ID`) REFERENCES `invoice` (`Invoice_ID`);

--
-- Constraints for table `clientspace`
--
ALTER TABLE `clientspace`
  ADD CONSTRAINT `clientspace_ibfk_1` FOREIGN KEY (`Space_ID`) REFERENCES `space` (`Space_ID`),
  ADD CONSTRAINT `clientspace_ibfk_2` FOREIGN KEY (`Client_ID`) REFERENCES `client` (`Client_ID`);

--
-- Constraints for table `handymanjob`
--
ALTER TABLE `handymanjob`
  ADD CONSTRAINT `fk_handymanjob_handyman` FOREIGN KEY (`Handyman_ID`) REFERENCES `handyman` (`Handyman_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_handymanjob_jobtype` FOREIGN KEY (`JobType_ID`) REFERENCES `jobtype` (`JobType_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `handymanjob_ibfk_1` FOREIGN KEY (`Handyman_ID`) REFERENCES `handyman` (`Handyman_ID`),
  ADD CONSTRAINT `handymanjob_ibfk_2` FOREIGN KEY (`JobType_ID`) REFERENCES `jobtype` (`JobType_ID`);

--
-- Constraints for table `invoice`
--
ALTER TABLE `invoice`
  ADD CONSTRAINT `fk_invoice_space` FOREIGN KEY (`Space_ID`) REFERENCES `space` (`Space_ID`);

--
-- Constraints for table `invoice_chat`
--
ALTER TABLE `invoice_chat`
  ADD CONSTRAINT `invoice_chat_ibfk_1` FOREIGN KEY (`Invoice_ID`) REFERENCES `invoice` (`Invoice_ID`) ON DELETE CASCADE;

--
-- Constraints for table `maintenancerequest`
--
ALTER TABLE `maintenancerequest`
  ADD CONSTRAINT `maintenancerequest_ibfk_1` FOREIGN KEY (`Client_ID`) REFERENCES `client` (`Client_ID`),
  ADD CONSTRAINT `maintenancerequest_ibfk_2` FOREIGN KEY (`Space_ID`) REFERENCES `space` (`Space_ID`),
  ADD CONSTRAINT `maintenancerequest_ibfk_3` FOREIGN KEY (`Handyman_ID`) REFERENCES `handyman` (`Handyman_ID`) ON DELETE CASCADE;

--
-- Constraints for table `maintenancerequeststatushistory`
--
ALTER TABLE `maintenancerequeststatushistory`
  ADD CONSTRAINT `maintenancerequeststatushistory_ibfk_1` FOREIGN KEY (`Request_ID`) REFERENCES `maintenancerequest` (`Request_ID`) ON DELETE CASCADE;

--
-- Constraints for table `rentalrequest`
--
ALTER TABLE `rentalrequest`
  ADD CONSTRAINT `rentalrequest_ibfk_1` FOREIGN KEY (`Client_ID`) REFERENCES `client` (`Client_ID`),
  ADD CONSTRAINT `rentalrequest_ibfk_2` FOREIGN KEY (`Space_ID`) REFERENCES `space` (`Space_ID`);

--
-- Constraints for table `space`
--
ALTER TABLE `space`
  ADD CONSTRAINT `space_ibfk_1` FOREIGN KEY (`SpaceType_ID`) REFERENCES `spacetype` (`SpaceType_ID`),
  ADD CONSTRAINT `space_ibfk_2` FOREIGN KEY (`UA_ID`) REFERENCES `useraccounts` (`UA_ID`);

--
-- Constraints for table `spaceavailability`
--
ALTER TABLE `spaceavailability`
  ADD CONSTRAINT `spaceavailability_ibfk_1` FOREIGN KEY (`Space_ID`) REFERENCES `space` (`Space_ID`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
